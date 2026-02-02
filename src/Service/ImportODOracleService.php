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
