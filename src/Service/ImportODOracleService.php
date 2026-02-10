<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class ImportODOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    /**
     * Vide la table OD_A_CHARGER avant l'import
     */
    public function clearOdACharger(): bool
    {
        try {
            $sql = "DELETE FROM OD_A_CHARGER";
            $this->getConnection()->executeStatement($sql);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Erreur lors du vidage de la table OD_A_CHARGER: " . $e->getMessage());
        }
    }

    /**
     * Insère les données CSV dans la table OD_A_CHARGER
     */
    public function insertDataToOdACharger(array $csvData): int
    {
        try {
            $this->getConnection()->beginTransaction();
            
            $insertedCount = 0;
            
            foreach ($csvData['data'] as $row) {
                $sql = <<<SQL
                INSERT INTO OD_A_CHARGER (
                    PAESI_CODEXT,
                    PAESI_LIBELLE,
                    PAESI_CODE,
                    PAESI_TYPE,
                    PAESI_ACTIF,
                    PAESI_ORDRE,
                    PAESI_COMMENTAIRE
                ) VALUES (
                    :paesi_codext,
                    :paesi_libelle,
                    :paesi_code,
                    :paesi_type,
                    :paesi_actif,
                    :paesi_ordre,
                    :paesi_commentaire
                )
                SQL;
                
                $params = [
                    'paesi_codext' => $row['PAESI_CODEXT'] ?? '',
                    'paesi_libelle' => $row['PAESI_LIBELLE'] ?? '',
                    'paesi_code' => $row['PAESI_CODE'] ?? '',
                    'paesi_type' => $row['PAESI_TYPE'] ?? '',
                    'paesi_actif' => $row['PAESI_ACTIF'] ?? '',
                    'paesi_ordre' => $row['PAESI_ORDRE'] ?? '',
                    'paesi_commentaire' => $row['PAESI_COMMENTAIRE'] ?? ''
                ];
                
                $this->getConnection()->executeStatement($sql, $params);
                $insertedCount++;
            }
            
            $this->getConnection()->commit();
            return $insertedCount;
            
        } catch (\Exception $e) {
            $this->getConnection()->rollBack();
            throw new \Exception("Erreur lors de l'insertion des données: " . $e->getMessage());
        }
    }

    /**
     * Supprime les espaces des codes PAESI_CODEXT
     */
    public function cleanPaesiCodes(): bool
    {
        try {
            $sql = "UPDATE OD_A_CHARGER SET PAESI_CODEXT = REPLACE(PAESI_CODEXT, ' ', '')";
            $this->getConnection()->executeStatement($sql);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Erreur lors du nettoyage des codes: " . $e->getMessage());
        }
    }

    /**
     * Récupère les données de la table OD_A_CHARGER pour prévisualisation
     */
    public function getOdAChargerData(): array
    {
        try {
            $sql = "SELECT * FROM OD_A_CHARGER ORDER BY PAESI_CODEXT";
            $result = $this->getConnection()->executeQuery($sql)->fetchAllAssociative();
            return $result;
        } catch (\Exception $e) {
            throw new \Exception("Erreur lors de la récupération des données: " . $e->getMessage());
        }
    }

    /**
     * Récupère les relevés de STAINS selon la requête fournie.
     */
    public function getRelevesStains(): array
    {
        $sql = <<<SQL
SELECT
    a.glcon_num AS CONTRAT,
    a.glcon_numver,
    (
        SELECT pae.paesi_codext
        FROM opulise.paesi pae
        WHERE pae.paesi_num = p.paesi_num
    ) AS ESI,
    CASE a.ECTIN_COD
        WHEN 'F' THEN 'C115'
        WHEN 'C' THEN 'C116'
    END AS RUBRIQUE,
    'AUTRE' AS FAMILLE,
    CASE a.ECTIN_COD
        WHEN 'F' THEN a.ECCON_VAL * 4.36
        WHEN 'C' THEN a.ECCON_VAL * 10.6
    END AS MONTANT,
    'EAU IND ' || a.ECCON_VAL || 'm3 - CPT:' || a.pains_cod AS LIBELLE_OD,
    ca.caint_lib1 AS LIBELLE_INTITULE,
    a.ECTIN_COD,
    a.PAINS_COD,
    a.ECCON_VAL,
    a.ECCON_DATREL
FROM opulise.eccon a,
     opulise.paidl p,
     opulise.glrec gle,
     opulise.glrfc glf,
     opulise.caint ca
WHERE p.paesi_num IN (
        SELECT pae2.paesi_num
        FROM opulise.paesi pae2
        WHERE SUBSTR(pae2.paesi_codext, 1, 6) IN ('ST4000','ST4100','ST4200')
    )
  AND a.ECCON_DATREL >= ADD_MONTHS(TRUNC(SYSDATE, 'MM'), -1)
  AND p.pains_cod = a.pains_cod
  AND p.paidl_ind = 'D'
  AND a.glcon_num IS NOT NULL
  AND glf.glcon_num = a.glcon_num
  AND glf.glrec_num = gle.glrec_num
  AND gle.caint_num = ca.caint_num
  -- Contrats de gardien qui ne payent pas l'eau
  AND a.glcon_num NOT IN (100983, 100499)
ORDER BY ESI
SQL;

        try {
            return $this->getConnection()->executeQuery($sql)->fetchAllAssociative();
        } catch (\Exception $e) {
            throw new \Exception("Erreur lors de la récupération des relevés de STAINS: " . $e->getMessage());
        }
    }

    /**
     * Exporte les relevés de STAINS en CSV (séparateur ';').
     */
    public function exportRelevesStainsCsv(): string
    {
        $rows = $this->getRelevesStains();

        $output = fopen('php://temp', 'r+');

        if (!empty($rows)) {
            $headers = array_keys($rows[0]);
            fputcsv($output, $headers, ';');

            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $row[$h] ?? '';
                }
                fputcsv($output, $line, ';');
            }
        } else {
            // En-tête générique si aucune ligne
            fputcsv($output, [
                'CONTRAT','GLCON_NUMVER','ESI','RUBRIQUE','FAMILLE',
                'MONTANT','LIBELLE_OD','LIBELLE_INTITULE',
                'ECTIN_COD','PAINS_COD','ECCON_VAL','ECCON_DATREL'
            ], ';');
        }

        rewind($output);
        return stream_get_contents($output) ?: '';
    }

    /**
     * Exécute la procédure d'intégration OD
     */
    public function executeIntegrationProcedure(string $userId): array
    {
        try {
            // Log de l'intégration
            $this->logIntegration($userId);
            
            // Appel de la procédure stockée ADD_OD
            $sql = "BEGIN ADD_OD(:user_id); END;";
            $this->getConnection()->executeStatement($sql, ['user_id' => $userId]);
            
            return [
                'success' => true,
                'message' => 'Intégration OD exécutée avec succès'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de l\'intégration: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Log de l'intégration pour le suivi
     */
    private function logIntegration(string $userId): void
    {
        try {
            $sql = "INSERT INTO INTEGRATION_OD_LOG (USER_ID, INTEGRATION_DATE, STATUS) VALUES (:user_id, SYSDATE, 'STARTED')";
            $this->getConnection()->executeStatement($sql, ['user_id' => $userId]);
        } catch (\Exception $e) {
            // Log silencieux - ne pas faire échouer l'intégration pour un problème de log
        }
    }

    /**
     * Valide les données avant intégration
     */
    public function validateData(): array
    {
        $errors = [];
        
        try {
            // Vérifier les doublons de PAESI_CODEXT
            $sql = "SELECT PAESI_CODEXT, COUNT(*) as count FROM OD_A_CHARGER GROUP BY PAESI_CODEXT HAVING COUNT(*) > 1";
            $duplicates = $this->getConnection()->executeQuery($sql)->fetchAllAssociative();
            
            if (!empty($duplicates)) {
                foreach ($duplicates as $dup) {
                    $errors[] = "Code PAESI_CODEXT dupliqué: " . $dup['PAESI_CODEXT'];
                }
            }
            
            // Vérifier les codes vides
            $sql = "SELECT COUNT(*) as count FROM OD_A_CHARGER WHERE PAESI_CODEXT IS NULL OR TRIM(PAESI_CODEXT) = ''";
            $emptyCodes = $this->getConnection()->executeQuery($sql)->fetchOne();
            
            if ($emptyCodes > 0) {
                $errors[] = "$emptyCodes ligne(s) avec PAESI_CODEXT vide";
            }
            
            // Vérifier les libellés vides
            $sql = "SELECT COUNT(*) as count FROM OD_A_CHARGER WHERE PAESI_LIBELLE IS NULL OR TRIM(PAESI_LIBELLE) = ''";
            $emptyLabels = $this->getConnection()->executeQuery($sql)->fetchOne();
            
            if ($emptyLabels > 0) {
                $errors[] = "$emptyLabels ligne(s) avec PAESI_LIBELLE vide";
            }
            
        } catch (\Exception $e) {
            $errors[] = "Erreur lors de la validation: " . $e->getMessage();
        }
        
        return $errors;
    }

    /**
     * Récupère le nombre de lignes dans OD_A_CHARGER
     */
    public function getDataCount(): int
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM OD_A_CHARGER";
            return (int) $this->getConnection()->executeQuery($sql)->fetchOne();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
