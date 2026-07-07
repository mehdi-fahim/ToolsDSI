<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportTresorerieOracleService
{
    private const INTEGRATION_TABLES = [
        'payement_fournisseur' => 'PAYFOURVIR',
        'saisi_oppo' => 'SAISIOPPO',
        'indem' => 'INDEM',
        'virement_loyer' => 'PAYFOURVIR',
    ];

    private const PREVIEW_SQL = [
        'payement_fournisseur' => 'SELECT LIBELLE, MONTANTTRN AS MONTANT, REFERENCE, DATEOP FROM PAYFOURVIR',
        'saisi_oppo' => 'SELECT LIBELLE, INTITULE, BENEFICIAIRE, RIB, MONTANT FROM SAISIOPPO',
        'indem' => 'SELECT INTITULE, NOM, LIBELLE, MONTANT FROM INDEM',
        'virement_loyer' => 'SELECT LIBELLE, MONTANTTRN AS MONTANT, REFERENCE, DATEOP FROM PAYFOURVIR',
    ];

    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function getClasseurs(): array
    {
        try {
            $rows = $this->getConnection()->executeQuery(
                'SELECT CACLA_NUM AS VALUE, CACLA_NUM AS LABEL FROM CACLA ORDER BY CACLA_NUM'
            )->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }

        return $this->mapOptions($rows, 'VALUE', 'LABEL');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function getTypesMouvement(string $classeur): array
    {
        if ($classeur === '') {
            return [];
        }

        $rows = $this->getConnection()->executeQuery(
            <<<SQL
SELECT CATMV_COD AS VALUE, CATMV_COD AS LABEL
FROM CATMV
WHERE CACLA_NUM = :classeur
  AND CATMV_ETA = 'A'
ORDER BY CATMV_LIB
SQL,
            ['classeur' => $classeur]
        )->fetchAllAssociative();

        return $this->mapOptions($rows, 'VALUE', 'LABEL');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function getActivites(): array
    {
        try {
            $rows = $this->getConnection()->executeQuery(
                'SELECT MGACT_COD AS VALUE, MGACT_COD AS LABEL FROM MGACT ORDER BY MGACT_COD'
            )->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        }

        return $this->mapOptions($rows, 'VALUE', 'LABEL');
    }

    /**
     * @return array{count: int, preview: list<array<string, mixed>>}
     */
    public function chargerFichierIntegration(
        UploadedFile $file,
        string $integrationType,
        string $userId
    ): array {
        if (!isset(self::INTEGRATION_TABLES[$integrationType])) {
            throw new \InvalidArgumentException('Type d\'intégration inconnu.');
        }

        $table = self::INTEGRATION_TABLES[$integrationType];
        $rows = $this->parseCsvFile($file);
        if ($rows === []) {
            throw new \RuntimeException('Aucun enregistrement à charger.');
        }

        if ($integrationType === 'virement_loyer') {
            $rows = $this->padRows($rows, 39);
        }

        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            $connection->executeStatement(sprintf('DELETE FROM %s', $table));
            $count = $this->insertRowsIntoTable($table, $rows);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return [
            'count' => $count,
            'preview' => $this->getPreviewRows($integrationType),
        ];
    }

    public function integrerFichier(
        string $integrationType,
        string $userId,
        int $exercice,
        string $classeur,
        string $societe,
        string $modePaiement,
        string $typeMouvement,
        string $activite,
        string $societeOppo,
        string $numeroLot
    ): void {
        $totieCod = $this->getTotieCod($userId);

        match ($integrationType) {
            'payement_fournisseur' => $this->getConnection()->executeStatement(
                'BEGIN INTEG_VIR_BANQ(:exercice, :classeur, :societe, :modePaiement, :typeMouvement, :totieCod, :activite); END;',
                [
                    'exercice' => $exercice,
                    'classeur' => $classeur,
                    'societe' => $societe,
                    'modePaiement' => $modePaiement,
                    'typeMouvement' => $typeMouvement,
                    'totieCod' => $totieCod,
                    'activite' => $activite,
                ]
            ),
            'saisi_oppo' => $this->getConnection()->executeStatement(
                'BEGIN INTEG_SAISIOPPO(:societe, :numeroLot, 6, :totieCod); END;',
                [
                    'societe' => $societeOppo,
                    'numeroLot' => $numeroLot,
                    'totieCod' => $totieCod,
                ]
            ),
            'indem' => $this->getConnection()->executeStatement(
                'BEGIN INTEG_INDEM(:societe, :numeroLot, 6, :totieCod); END;',
                [
                    'societe' => $societeOppo,
                    'numeroLot' => $numeroLot,
                    'totieCod' => $totieCod,
                ]
            ),
            'virement_loyer' => $this->getConnection()->executeStatement(
                'BEGIN INTEG_PAY_LOC(:exercice, :classeur, :societe, :modePaiement, :typeMouvement, :totieCod, :activite); END;',
                [
                    'exercice' => $exercice,
                    'classeur' => $classeur,
                    'societe' => $societe,
                    'modePaiement' => $modePaiement,
                    'typeMouvement' => $typeMouvement,
                    'totieCod' => $totieCod,
                    'activite' => $activite,
                ]
            ),
            default => throw new \InvalidArgumentException('Type d\'intégration inconnu.'),
        };
    }

    /**
     * @return array{annee: int, numeroTraitement: string}
     */
    public function chargerFichierCacos(UploadedFile $file): array
    {
        $rows = $this->parseCsvFile($file);
        if ($rows === []) {
            throw new \RuntimeException('Le fichier CACOS est vide.');
        }

        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            $connection->executeStatement('DELETE FROM CACOS048_0');
            $this->insertRowsIntoTable('CACOS048_0', $rows);

            $connection->executeStatement('DELETE FROM CACOS048');
            $connection->executeStatement(
                'INSERT INTO CACOS048 SELECT intitule, nom, parti, ctx, tem12mois, temexclu, date_comptable, montant, DATE_DEB_INTITULE, DATE_FIN_INTITULE, NOMBRE_MOIS_PRESENCE, ESI, CODE_ESO, ESO FROM CACOS048_0'
            );

            $annee = (int) (new \DateTimeImmutable())->format('Y') - 1;
            $numeroTraitement = (string) $connection->executeQuery(
                'SELECT NVL(MAX(CACDE_NUM), 0) FROM CACDD'
            )->fetchOne();

            $connection->commit();

            return [
                'annee' => $annee,
                'numeroTraitement' => $numeroTraitement,
            ];
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    public function exportCacosCsv(int $anneeTraitement, string $numeroTraitement): string
    {
        $sql = <<<SQL
SELECT
    C.CAINT_NUM AS INTITULE,
    a.glrec_intcou1 AS NOM,
    C.PAESI_CODEXT AS ESI,
    b.NB_MOIS_PRESENCE AS NB_MOIS_PRESENCE,
    b.montant_dette AS DETTE,
    (
        SELECT SUM(xa.carec_mntr)
        FROM carec xa, paesi xb, glrub xc
        WHERE xa.paesi_num = xb.paesi_num
          AND xa.glrub_cod = xc.glrub_cod
          AND xa.carec_datref BETWEEN TO_DATE(:dateDebut, 'DD/MM/YYYY') AND TO_DATE(:dateFin, 'DD/MM/YYYY')
          AND xc.glfam_cod IN ('AIDES', 'CHARG', 'LOYER', 'RLS')
          AND xa.carec_statut <> 'A'
          AND xa.caint_num = a.caint_num
        GROUP BY a.caint_num
    ) AS QUITTANCEMENT_NORMATIF,
    (
        SELECT CASE WHEN SUM(xa.CAREC_TAUXTVA) > 0 THEN 'OUI' ELSE 'NON' END
        FROM carec xa, paesi xb, glrub xc
        WHERE xa.paesi_num = xb.paesi_num
          AND xa.glrub_cod = xc.glrub_cod
          AND xa.carec_datref BETWEEN TO_DATE(:dateDebut, 'DD/MM/YYYY') AND TO_DATE(:dateFin, 'DD/MM/YYYY')
          AND xc.glfam_cod IN ('AIDES', 'CHARG', 'LOYER', 'RLS')
          AND xa.carec_statut <> 'A'
          AND xa.caint_num = a.caint_num
        GROUP BY a.caint_num
    ) AS TVA_QUITTANCEMENT_NORMATIF,
    b.PARTI AS PARTI,
    b.eso,
    GET_PERIODICITE(F_CONTRAT_ESI(paesi_num, TO_DATE(:dateFin, 'DD/MM/YYYY'))) AS PERIODICITE_DE_FACTURATION
FROM CACDD C, glrec a, cacos048 b
WHERE c.caint_num = a.caint_num
  AND c.CACDE_NUM = :numeroTraitement
  AND b.intitule = c.caint_num
SQL;

        $dateDebut = sprintf('01/12/%04d', $anneeTraitement);
        $dateFin = sprintf('31/12/%04d', $anneeTraitement);

        $rows = $this->getConnection()->executeQuery($sql, [
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'numeroTraitement' => $numeroTraitement,
        ])->fetchAllAssociative();

        return $this->rowsToCsv($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPreviewRows(string $integrationType): array
    {
        if (!isset(self::PREVIEW_SQL[$integrationType])) {
            return [];
        }

        return $this->getConnection()
            ->executeQuery(self::PREVIEW_SQL[$integrationType])
            ->fetchAllAssociative();
    }

    private function getTotieCod(string $userId): string
    {
        $userId = strtoupper(trim($userId));
        $totieCod = $this->getConnection()->executeQuery(
            'SELECT totie_cod FROM mguti WHERE mguti_cod = :userId',
            ['userId' => $userId]
        )->fetchOne();

        if ($totieCod === false || $totieCod === null || $totieCod === '') {
            throw new \RuntimeException('Impossible de récupérer le tiers utilisateur.');
        }

        return (string) $totieCod;
    }

    /**
     * @return list<list<string>>
     */
    private function parseCsvFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new \RuntimeException('Fichier CSV invalide.');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Impossible de lire le fichier CSV.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return [];
        }

        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($this->rowHasValue($row)) {
                $rows[] = array_map(static fn ($value): string => trim((string) $value), $row);
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param list<list<string>> $rows
     */
    private function insertRowsIntoTable(string $table, array $rows): int
    {
        $columns = $this->getTableColumns($table);
        if ($columns === []) {
            throw new \RuntimeException(sprintf('Impossible de lire la structure de la table %s.', $table));
        }

        $placeholders = implode(', ', array_map(static fn (string $col): string => ':' . strtolower($col), $columns));
        $columnList = implode(', ', $columns);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, $columnList, $placeholders);

        $inserted = 0;
        foreach ($rows as $row) {
            $params = [];
            foreach ($columns as $index => $column) {
                $params[strtolower($column)] = $row[$index] ?? '';
            }
            $this->getConnection()->executeStatement($sql, $params);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * @return list<string>
     */
    private function getTableColumns(string $table): array
    {
        $rows = $this->getConnection()->executeQuery(
            <<<SQL
SELECT column_name
FROM user_tab_columns
WHERE table_name = :tableName
ORDER BY column_id
SQL,
            ['tableName' => strtoupper($table)]
        )->fetchFirstColumn();

        return array_map('strval', $rows);
    }

    /**
     * @param list<list<string>> $rows
     * @return list<list<string>>
     */
    private function padRows(array $rows, int $targetCount): array
    {
        return array_map(static function (array $row) use ($targetCount): array {
            while (count($row) < $targetCount) {
                $row[] = '';
            }

            return $row;
        }, $rows);
    }

    /**
     * @param list<string|null> $row
     */
    private function rowHasValue(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function rowsToCsv(array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        if ($rows !== []) {
            $columns = array_keys($rows[0]);
            fputcsv($output, $columns, ';');
            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $line[] = $row[$column] ?? $row[strtolower($column)] ?? '';
                }
                fputcsv($output, $line, ';');
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return "\xEF\xBB\xBF" . ($csv ?: '');
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{value: string, label: string}>
     */
    private function mapOptions(array $rows, string $valueKey, string $labelKey): array
    {
        $options = [];
        foreach ($rows as $row) {
            $value = (string) ($row[$valueKey] ?? $row[strtolower($valueKey)] ?? '');
            if ($value === '') {
                continue;
            }
            $label = (string) ($row[$labelKey] ?? $row[strtolower($labelKey)] ?? $value);
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
