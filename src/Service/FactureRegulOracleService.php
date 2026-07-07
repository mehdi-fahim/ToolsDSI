<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class FactureRegulOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    /**
     * @return array{
     *     found: bool,
     *     periode_actuelle: ?string,
     *     facture_regularisee: bool,
     *     ventilations: list<array{
     *         mgnad_cod: string,
     *         glper_cod: string,
     *         tafav_datregu: ?string,
     *         gldav_num: ?string,
     *         info_regul: ?string,
     *         can_modify: bool
     *     }>
     * }
     */
    public function search(int $exercice, string $numeroFacture): array
    {
        $numeroFacture = trim($numeroFacture);
        if ($numeroFacture === '') {
            throw new \InvalidArgumentException('Le numéro de facture est obligatoire.');
        }

        $periodeActuelle = $this->getConnection()->executeQuery(
            'SELECT GLPER_COD FROM TAFAC WHERE ICEXE_NUM = :exercice AND TAFAC_NUM = :numero',
            [
                'exercice' => $exercice,
                'numero' => $numeroFacture,
            ]
        )->fetchOne();

        if ($periodeActuelle === false) {
            return [
                'found' => false,
                'periode_actuelle' => null,
                'facture_regularisee' => false,
                'ventilations' => [],
            ];
        }

        $ventilations = $this->getVentilations($exercice, $numeroFacture);
        $factureRegularisee = $this->isFactureRegularisee($ventilations);

        return [
            'found' => true,
            'periode_actuelle' => $periodeActuelle !== null ? (string) $periodeActuelle : '',
            'facture_regularisee' => $factureRegularisee,
            'ventilations' => $ventilations,
        ];
    }

    public function updateFacturePeriode(int $exercice, string $numeroFacture, string $periode, string $userId): void
    {
        $numeroFacture = trim($numeroFacture);
        $periode = trim($periode);
        if ($periode === '') {
            throw new \InvalidArgumentException('La période de régularisation est obligatoire.');
        }

        $search = $this->search($exercice, $numeroFacture);
        if (!$search['found']) {
            throw new \InvalidArgumentException('Facture introuvable.');
        }
        if ($search['facture_regularisee']) {
            throw new \InvalidArgumentException('Cette facture est régularisée : la période ne peut plus être modifiée.');
        }

        $updated = $this->getConnection()->executeStatement(
            <<<SQL
UPDATE TAFAC
SET GLPER_COD = :periode,
    TAFAC_DATMAJ = SYSDATE,
    TAFAC_UTIMAJ = SUBSTR(:utimaj, 1, 12)
WHERE ICEXE_NUM = :exercice
  AND TAFAC_NUM = :numero
SQL,
            [
                'periode' => $periode,
                'utimaj' => $this->formatUtimaj($userId),
                'exercice' => $exercice,
                'numero' => $numeroFacture,
            ]
        );

        if ($updated === 0) {
            throw new \RuntimeException('Aucune ligne mise à jour sur la facture.');
        }
    }

    public function updateVentilationPeriode(
        int $exercice,
        string $numeroFacture,
        string $mgnadCod,
        string $periode,
        string $userId
    ): void {
        $numeroFacture = trim($numeroFacture);
        $mgnadCod = trim($mgnadCod);
        $periode = trim($periode);

        if ($mgnadCod === '' || $periode === '') {
            throw new \InvalidArgumentException('La ventilation et la période sont obligatoires.');
        }

        $ventilation = $this->getVentilation($exercice, $numeroFacture, $mgnadCod);
        if ($ventilation === null) {
            throw new \InvalidArgumentException('Ventilation introuvable.');
        }
        if (!$ventilation['can_modify']) {
            throw new \InvalidArgumentException('Cette ventilation est régularisée : la période ne peut plus être modifiée.');
        }

        $updated = $this->getConnection()->executeStatement(
            <<<SQL
UPDATE TAFAV
SET GLPER_COD = :periode,
    TAFAV_DATMAJ = SYSDATE,
    TAFAV_UTIMAJ = SUBSTR(:utimaj, 1, 12)
WHERE ICEXE_NUM = :exercice
  AND TAFAC_NUM = :numero
  AND MGNAD_COD = :mgnad_cod
  AND TAFAV_DATREGU IS NULL
SQL,
            [
                'periode' => $periode,
                'utimaj' => $this->formatUtimaj($userId),
                'exercice' => $exercice,
                'numero' => $numeroFacture,
                'mgnad_cod' => $mgnadCod,
            ]
        );

        if ($updated === 0) {
            throw new \RuntimeException('Aucune ligne mise à jour sur la ventilation.');
        }
    }

    /**
     * @return list<array{
     *     mgnad_cod: string,
     *     glper_cod: string,
     *     tafav_datregu: ?string,
     *     gldav_num: ?string,
     *     info_regul: ?string,
     *     can_modify: bool
     * }>
     */
    private function getVentilations(int $exercice, string $numeroFacture): array
    {
        $rows = $this->getConnection()->executeQuery(
            <<<SQL
SELECT MGNAD_COD,
       GLPER_COD,
       TAFAV_DATREGU,
       GLDAV_NUM
FROM OPULISE.TAFAV
WHERE ICEXE_NUM = :exercice
  AND TAFAC_NUM = :numero
ORDER BY MGNAD_COD
SQL,
            [
                'exercice' => $exercice,
                'numero' => $numeroFacture,
            ]
        )->fetchAllAssociative();

        $ventilations = [];
        foreach ($rows as $row) {
            $ventilations[] = $this->mapVentilationRow($row);
        }

        return $ventilations;
    }

    /**
     * @return array{
     *     mgnad_cod: string,
     *     glper_cod: string,
     *     tafav_datregu: ?string,
     *     gldav_num: ?string,
     *     info_regul: ?string,
     *     can_modify: bool
     * }|null
     */
    private function getVentilation(int $exercice, string $numeroFacture, string $mgnadCod): ?array
    {
        $row = $this->getConnection()->executeQuery(
            <<<SQL
SELECT MGNAD_COD,
       GLPER_COD,
       TAFAV_DATREGU,
       GLDAV_NUM
FROM OPULISE.TAFAV
WHERE ICEXE_NUM = :exercice
  AND TAFAC_NUM = :numero
  AND MGNAD_COD = :mgnad_cod
SQL,
            [
                'exercice' => $exercice,
                'numero' => $numeroFacture,
                'mgnad_cod' => $mgnadCod,
            ]
        )->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->mapVentilationRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *     mgnad_cod: string,
     *     glper_cod: string,
     *     tafav_datregu: ?string,
     *     gldav_num: ?string,
     *     info_regul: ?string,
     *     can_modify: bool
     * }
     */
    private function mapVentilationRow(array $row): array
    {
        $mgnadCod = (string) ($row['MGNAD_COD'] ?? $row['mgnad_cod'] ?? '');
        $glperCod = (string) ($row['GLPER_COD'] ?? $row['glper_cod'] ?? '');
        $datRegu = $row['TAFAV_DATREGU'] ?? $row['tafav_datregu'] ?? null;
        $davNum = $row['GLDAV_NUM'] ?? $row['gldav_num'] ?? null;

        $datReguStr = $datRegu !== null && $datRegu !== '' ? (string) $datRegu : null;
        $davNumStr = $davNum !== null && $davNum !== '' ? (string) $davNum : null;
        $canModify = $datReguStr === null;

        $infoRegul = null;
        if (!$canModify) {
            $infoRegul = sprintf('Régularisé le %s n° DAV: %s', $datReguStr, $davNumStr ?? '');
        }

        return [
            'mgnad_cod' => $mgnadCod,
            'glper_cod' => $glperCod,
            'tafav_datregu' => $datReguStr,
            'gldav_num' => $davNumStr,
            'info_regul' => $infoRegul,
            'can_modify' => $canModify,
        ];
    }

    /**
     * @param list<array{can_modify: bool}> $ventilations
     */
    private function isFactureRegularisee(array $ventilations): bool
    {
        if ($ventilations === []) {
            return false;
        }

        foreach ($ventilations as $ventilation) {
            if ($ventilation['can_modify']) {
                return false;
            }
        }

        return true;
    }

    private function formatUtimaj(string $userId): string
    {
        return substr('ODSI_' . strtoupper(trim($userId)), 0, 12);
    }
}
