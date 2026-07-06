<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class LocataireOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    /**
     * Recherche les contrats actifs liés à un numéro d'intitulé (caint_num).
     *
     * @return list<array{CACTR_NUM: string}>
     */
    public function searchByIntitule(string $intitule): array
    {
        $intitule = trim($intitule);
        if ($intitule === '') {
            return [];
        }

        $sql = <<<SQL
SELECT DISTINCT cactr_num AS CACTR_NUM
FROM opulise.CACTR
WHERE CACTR_DTF IS NULL
  AND CACTR_TEMP = 'T'
  AND caint_num = :intitule
ORDER BY 1
SQL;

        return $this->getConnection()->executeQuery($sql, [
            'intitule' => $intitule,
        ])->fetchAllAssociative();
    }

    /**
     * Recherche par code ESI (paesi_codext). Le caractère * est interprété comme joker (%).
     *
     * @return list<array{NOM_LOC: string, ESI: string, TIERS: string, CONTRAT: string}>
     */
    public function searchByEsi(string $esi): array
    {
        $pattern = $this->buildLikePattern($esi);
        if ($pattern === null) {
            return [];
        }

        $sql = <<<SQL
SELECT DISTINCT
    F_Libelle_Occupant(paesi_num, SYSDATE) AS NOM_LOC,
    paesi_codext AS ESI,
    NVL(
        get_signataire((SELECT GET_CONTRAT_DT(paesi_num, SYSDATE) FROM DUAL)),
        (SELECT TOTIE_COD FROM glocc
         WHERE glcon_num = (SELECT GET_CONTRAT_DT(paesi_num, SYSDATE) FROM DUAL)
           AND ROWNUM = 1)
    ) AS TIERS,
    F_CONTRAT_ESI(paesi_num, SYSDATE) AS CONTRAT
FROM syn_pat a
WHERE paesi_codext LIKE :pattern
  AND F_Libelle_Occupant(paesi_num, SYSDATE) IS NOT NULL
  AND F_CONTRAT_ESI(paesi_num, SYSDATE) IS NOT NULL
ORDER BY 1
SQL;

        return $this->getConnection()->executeQuery($sql, [
            'pattern' => $pattern,
        ])->fetchAllAssociative();
    }

    /**
     * Recherche par nom du locataire (F_Libelle_Occupant). Le caractère * est interprété comme joker (%).
     *
     * @return list<array{NOM_LOC: string, ESI: string, TIERS: string, CONTRAT: string}>
     */
    public function searchByNom(string $nom): array
    {
        $pattern = $this->buildLikePattern($nom);
        if ($pattern === null) {
            return [];
        }

        $sql = <<<SQL
SELECT
    F_Libelle_Occupant(paesi_num, SYSDATE) AS NOM_LOC,
    paesi_codext AS ESI,
    NVL(
        get_signataire((SELECT GET_CONTRAT_DT(paesi_num, SYSDATE) FROM DUAL)),
        (SELECT TOTIE_COD FROM glocc
         WHERE glcon_num = (SELECT GET_CONTRAT_DT(paesi_num, SYSDATE) FROM DUAL)
           AND ROWNUM = 1)
    ) AS TIERS,
    F_CONTRAT_ESI(paesi_num, SYSDATE) AS CONTRAT
FROM syn_pat a
WHERE UPPER(F_Libelle_Occupant(paesi_num, SYSDATE)) LIKE :pattern
  AND F_CONTRAT_ESI(paesi_num, SYSDATE) IS NOT NULL
ORDER BY 1
SQL;

        return $this->getConnection()->executeQuery($sql, [
            'pattern' => $pattern,
        ])->fetchAllAssociative();
    }

    /**
     * Construit un motif LIKE Oracle à partir de la saisie utilisateur (* → %).
     */
    private function buildLikePattern(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = strtoupper(str_replace('*', '%', $value));

        return '%' . $value . '%';
    }

    /**
     * Charge la fiche locataire à partir d'un n° de contrat ou d'un n° de tiers.
     *
     * @return array{
     *     mode: string,
     *     contrat: string|null,
     *     tiers: string|null,
     *     intitule: string|null,
     *     nomChq: string|null,
     *     adresse: string|null,
     *     signataires: list<array{NOM: string, TODPP_NUMPOR: string|null, NO_TIERS: string}>,
     *     encaissements: list<array{TYPE_PAIEMENT: string, MONTANT_REGLE: string|float|null, DATE_PREENC: string|null, SAISI_PAR: string|null}>,
     *     factures: list<array{TYPE_FACT: string, MNT_ECH: string|float|null}>,
     *     mntEcheance: float,
     *     prelevementAuto: bool,
     *     dette: float|null,
     *     hideEncaissementForm: bool
     * }
     */
    public function getFiche(?string $contrat, ?string $tiers): array
    {
        $contrat = trim((string) $contrat);
        $tiers = trim((string) $tiers);

        if ($contrat !== '') {
            return $this->buildFicheFromContrat($contrat);
        }
        if ($tiers !== '') {
            return $this->buildFicheFromTiers($tiers);
        }

        throw new \InvalidArgumentException('Un numéro de contrat ou de tiers est requis.');
    }

    public function updateSignataireTelephone(string $noTiers, string $telephone): void
    {
        $telephone = $this->sanitizeTelephone($telephone);
        $noTiers = trim($noTiers);

        if ($noTiers === '') {
            throw new \InvalidArgumentException('Le numéro de tiers est requis.');
        }

        $sql = 'UPDATE TODPP SET todpp_numpor = :tel WHERE TOTIE_cod = :tiers';
        $this->getConnection()->executeStatement($sql, [
            'tel' => $telephone,
            'tiers' => $noTiers,
        ]);
    }

    private function buildFicheFromContrat(string $contrat): array
    {
        $intitule = $this->fetchScalar(
            'SELECT GET_INTITULE(:contrat, 0) FROM DUAL',
            ['contrat' => $contrat]
        );
        $nomChq = $this->fetchScalar(
            'SELECT GLREC_INTCOU1 FROM opulise.glrec WHERE caint_num = :intitule',
            ['intitule' => $intitule]
        );
        $adresse = $this->fetchScalar(
            "SELECT FGETADRCTR(:contrat, 0, SYSDATE, 'L') FROM DUAL",
            ['contrat' => $contrat]
        );

        $signataires = $this->getConnection()->executeQuery(
            <<<SQL
SELECT MGQUA_COD || ' ' || TODPP_PRE || ' ' || TODPP_NOM AS NOM,
       mef_no_tel(TODPP_NUMPOR) AS TODPP_NUMPOR,
       a.totie_cod AS NO_TIERS
FROM opulise.todpp a, opulise.glcsi b
WHERE b.totie_cod = a.totie_cod
  AND glcon_num = :contrat
SQL,
            ['contrat' => $contrat]
        )->fetchAllAssociative();

        return $this->enrichFiche([
            'mode' => 'contrat',
            'contrat' => $contrat,
            'tiers' => null,
            'intitule' => $intitule,
            'nomChq' => $nomChq,
            'adresse' => $adresse,
            'signataires' => $signataires,
        ]);
    }

    private function buildFicheFromTiers(string $tiers): array
    {
        $intitule = $this->fetchScalar(
            'SELECT caint_num FROM opulise.CAICL WHERE totie_cod = :tiers',
            ['tiers' => $tiers]
        );
        $nomChq = $this->fetchScalar(
            'SELECT GLREC_INTCOU1 FROM opulise.glrec WHERE caint_num = :intitule',
            ['intitule' => $intitule]
        );
        $adresse = $this->fetchScalar(
            "SELECT Get_Adr_Facturation_Tiers(:tiers, 'COMPLET') FROM DUAL",
            ['tiers' => $tiers]
        );

        $signataires = $this->getConnection()->executeQuery(
            <<<SQL
SELECT MGQUA_COD || ' ' || TODPP_PRE || ' ' || TODPP_NOM AS NOM,
       mef_no_tel(TODPP_NUMPOR) AS TODPP_NUMPOR,
       totie_cod AS NO_TIERS
FROM opulise.todpp a
WHERE a.totie_cod = :tiers
SQL,
            ['tiers' => $tiers]
        )->fetchAllAssociative();

        return $this->enrichFiche([
            'mode' => 'tiers',
            'contrat' => null,
            'tiers' => $tiers,
            'intitule' => $intitule,
            'nomChq' => $nomChq,
            'adresse' => $adresse,
            'signataires' => $signataires,
        ]);
    }

    /**
     * @param array<string, mixed> $base
     */
    private function enrichFiche(array $base): array
    {
        $intitule = (string) ($base['intitule'] ?? '');
        $encaissements = [];
        $factures = [];
        $mntEcheance = 0.0;
        $prelevementAuto = false;
        $dette = null;
        $hideEncaissementForm = false;

        if ($intitule !== '') {
            $encaissements = $this->getConnection()->executeQuery(
                <<<SQL
SELECT TYPE_PAIEMENT, MONTANT_REGLE, DATE_PREENC, SAISI_PAR
FROM (
    SELECT TRIM(p.TYPE_PAIEMENT || ' ' || p.NUM_CHQ) AS TYPE_PAIEMENT,
           p.MONTANT_REGLE,
           TO_CHAR(p.DATE_PREENC, 'DD/MM/YYYY') AS DATE_PREENC,
           p.SAISI_PAR
    FROM PREENC_SAISIE p
    WHERE p.intitule = TO_NUMBER(:intitule)
    ORDER BY p.DATE_PREENC DESC, p.ROWID DESC
)
WHERE ROWNUM < 6
SQL,
                ['intitule' => $intitule]
            )->fetchAllAssociative();

            $factures = $this->getConnection()->executeQuery(
                <<<SQL
SELECT CASE TYPE_FACT
           WHEN 'ECHEANCE' THEN 'Echéance'
           WHEN 'FACTSPECIALE' THEN 'Facture spéciale'
           WHEN 'REGULPROV' THEN 'Régularisation'
           ELSE 'Autre'
       END AS TYPE_FACT,
       MNT_ECH
FROM INT_PRE_ENC
WHERE intitule = :intitule
SQL,
                ['intitule' => $intitule]
            )->fetchAllAssociative();

            foreach ($factures as &$facture) {
                $montant = $this->parseMontant($facture['MNT_ECH'] ?? null);
                $facture['MNT_ECH'] = $montant;
                $mntEcheance += $montant ?? 0.0;
            }
            unset($facture);

            foreach ($encaissements as &$encaissement) {
                $encaissement['MONTANT_REGLE'] = $this->parseMontant($encaissement['MONTANT_REGLE'] ?? null);
            }
            unset($encaissement);

            if ($factures === []) {
                $preleve = $this->fetchScalar(
                    'SELECT GET_PRELEVE(:intitule) FROM DUAL',
                    ['intitule' => $intitule]
                );
                $prelevementAuto = (int) $preleve === 1;
            }

            $detteValue = $this->fetchScalar(
                'SELECT get_dette(:intitule) FROM DUAL',
                ['intitule' => $intitule]
            );
            $dette = $this->parseMontant($detteValue);

            $lastMonth = $this->fetchScalar(
                <<<SQL
SELECT MOIS FROM (
    SELECT TO_CHAR(p.DATE_PREENC, 'MM') AS MOIS
    FROM PREENC_SAISIE p
    WHERE p.intitule = TO_NUMBER(:intitule)
    ORDER BY p.DATE_PREENC DESC, p.ROWID DESC
)
WHERE ROWNUM = 1
SQL,
                ['intitule' => $intitule]
            );
            $currentMonth = $this->fetchScalar("SELECT TO_CHAR(SYSDATE, 'MM') FROM DUAL");
            $hideEncaissementForm = $lastMonth !== null && $lastMonth === $currentMonth;
        }

        return array_merge($base, [
            'encaissements' => $encaissements,
            'factures' => $factures,
            'mntEcheance' => $mntEcheance,
            'prelevementAuto' => $prelevementAuto,
            'dette' => $dette,
            'hideEncaissementForm' => $hideEncaissementForm,
        ]);
    }

    /**
     * Enregistre un encaissement dans PREENC_SAISIE (équivalent validation_preenc.aspx).
     */
    public function insertEncaissement(
        string $intitule,
        string $typePay,
        string $montant,
        string $nomChq,
        string $numChq,
        string $userId
    ): void {
        $intitule = trim($intitule);
        $nomChq = str_replace("'", ' ', trim((string) $nomChq));
        $numChq = trim((string) $numChq);
        $typePay = $this->normalizeTypePaiement($typePay);
        $montantFloat = $this->parseMontant($montant);

        if ($intitule === '' || $typePay === '' || $montantFloat === null) {
            throw new \InvalidArgumentException('Intitulé, type de paiement et montant sont requis.');
        }

        // Montant au format point décimal pour TO_NUMBER Oracle (évite ORA-01722 avec le driver OCI)
        $montantOracle = number_format($montantFloat, 2, '.', '');

        $sql = <<<SQL
INSERT INTO PREENC_SAISIE (INTITULE, TYPE_PAIEMENT, ECHEANCE, MONTANT_REGLE, NOM_CHQ, NUM_CHQ, DATE_PREENC, SAISI_PAR)
VALUES (
    TO_NUMBER(:intitule),
    :type_pay,
    'ANTE',
    TO_NUMBER(:montant, 'FM999999990.00'),
    :nom_chq,
    :num_chq,
    SYSDATE,
    :user_id
)
SQL;

        $this->getConnection()->executeStatement($sql, [
            'intitule' => $intitule,
            'type_pay' => $typePay,
            'montant' => $montantOracle,
            'nom_chq' => $nomChq,
            'num_chq' => $numChq,
            'user_id' => strtoupper(trim($userId)),
        ], [
            'intitule' => ParameterType::STRING,
            'type_pay' => ParameterType::STRING,
            'montant' => ParameterType::STRING,
            'nom_chq' => ParameterType::STRING,
            'num_chq' => ParameterType::STRING,
            'user_id' => ParameterType::STRING,
        ]);
    }

    /**
     * Codes TYPE_PAIEMENT sur 6 caractères max : CHQ, TIP, TIPCHQ.
     */
    public function normalizeTypePaiement(string $typePay): string
    {
        $key = strtoupper(str_replace(' ', '', trim($typePay)));

        return match ($key) {
            'CHEQUE', 'CHQ', 'CHÈQUE' => 'CHQ',
            'TIP' => 'TIP',
            'TIPCHEQUE', 'TIPCHQ', 'TIPCHÈQUE' => 'TIPCHQ',
            default => substr($key, 0, 6),
        };
    }

    public function getTypePaiementLabel(string $typePay): string
    {
        return match ($this->normalizeTypePaiement($typePay)) {
            'CHQ' => 'Chèque',
            'TIP' => 'TIP',
            'TIPCHQ' => 'TIP Chèque',
            default => $typePay,
        };
    }

    public function parseMontant(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);
        $str = str_replace(["\xc2\xa0", ' '], '', $str);
        $str = str_replace('€', '', $str);

        if (str_contains($str, ',') && str_contains($str, '.')) {
            $str = str_replace('.', '', $str);
        }
        $str = str_replace(',', '.', $str);

        return is_numeric($str) ? (float) $str : null;
    }

    public function formatMontant(mixed $value): string
    {
        $montant = $this->parseMontant($value);
        if ($montant === null) {
            return '-';
        }

        return number_format($montant, 2, ',', ' ') . ' €';
    }

    private function fetchScalar(string $sql, array $params = []): ?string
    {
        $result = $this->getConnection()->executeQuery($sql, $params)->fetchOne();
        if ($result === false || $result === null) {
            return null;
        }

        return (string) $result;
    }

    private function sanitizeTelephone(string $telephone): string
    {
        return str_replace([' ', '.', ',', '/'], '', trim($telephone));
    }
}
