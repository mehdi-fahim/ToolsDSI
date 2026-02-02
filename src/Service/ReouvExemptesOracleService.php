<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use App\Service\DatabaseConnectionResolver;

class ReouvExemptesOracleService
{
    public function __construct(
        private DatabaseConnectionResolver $connectionResolver
    ) {}

    private function getConnection(): Connection
    {
        return $this->getConnection()Resolver->getConnection();
    }

    /**
     * Réouvre les étapes exemptées d'un processus de proposition
     */
    public function reopenExemptedSteps(int $numeroProposition): array
    {
        try {
            $this->getConnection()->beginTransaction();
            
            // Première requête : mettre à jour WXPRO
            $sql1 = "UPDATE wxpro
                     SET WXPRO_ETA = 'E'
                     WHERE wxpro_num = (
                         SELECT wxpro_num
                         FROM (
                             SELECT a.wxpro_num
                             FROM wxpdo a, wxpro b
                             WHERE a.wxpro_num = b.wxpro_num
                             AND WFNAT_COD = 'PROP'
                             AND BIDOS_NUM LIKE 'AC/PROP/' || ? || '%'
                             ORDER BY WXPDO_DAT DESC
                         )
                         WHERE rownum = 1
                     )";
            
            $rowsAffected1 = $this->getConnection()->executeStatement($sql1, [$numeroProposition]);
            
            // Deuxième requête : mettre à jour WXETP
            $sql2 = "UPDATE wxetp
                     SET WFDEC_COD = null, WXETP_ETA = 'N', WXETP_ACCES = 'C'
                     WHERE wxpro_num = (
                         SELECT wxpro_num
                         FROM (
                             SELECT a.wxpro_num
                             FROM wxpdo a, wxpro b
                             WHERE a.wxpro_num = b.wxpro_num
                             AND WFNAT_COD = 'PROP'
                             AND BIDOS_NUM LIKE 'AC/PROP/' || ? || '%'
                             ORDER BY WXPDO_DAT DESC
                         )
                         WHERE rownum = 1
                     )
                     AND WFDEC_COD = '_EXS'";
            
            $rowsAffected2 = $this->getConnection()->executeStatement($sql2, [$numeroProposition]);
            
            $this->getConnection()->commit();
            
            $totalRows = $rowsAffected1 + $rowsAffected2;
            
            return [
                'success' => true,
                'rows_affected' => $totalRows,
                'wxpro_updated' => $rowsAffected1,
                'wxetp_updated' => $rowsAffected2,
                'message' => $totalRows > 0 
                    ? "✅ Réouverture effectuée avec succès : {$rowsAffected1} processus mis à jour, {$rowsAffected2} étape(s) réouverte(s)." 
                    : "ℹ️ Aucune étape exemptée trouvée pour cette proposition."
            ];
        } catch (\Exception $e) {
            $this->getConnection()->rollBack();
            return [
                'success' => false,
                'error' => 'Erreur lors de la réouverture des étapes exemptées: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si une proposition existe
     */
    public function propositionExists(int $numeroProposition): bool
    {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM wxpdo a, wxpro b
                    WHERE a.wxpro_num = b.wxpro_num
                    AND WFNAT_COD = 'PROP'
                    AND BIDOS_NUM LIKE 'AC/PROP/' || ? || '%'";
            
            $count = $this->getConnection()->executeQuery($sql, [$numeroProposition])->fetchOne();
            return (int)$count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Récupère les informations de la proposition
     */
    public function getPropositionInfo(int $numeroProposition): ?array
    {
        try {
            $sql = "SELECT a.wxpro_num, b.BIDOS_NUM, b.WXPDO_DAT
                    FROM wxpdo b, wxpro a
                    WHERE a.wxpro_num = b.wxpro_num
                    AND WFNAT_COD = 'PROP'
                    AND BIDOS_NUM LIKE 'AC/PROP/' || ? || '%'
                    ORDER BY WXPDO_DAT DESC
                    FETCH FIRST 1 ROWS ONLY";
            
            $result = $this->getConnection()->executeQuery($sql, [$numeroProposition])->fetchAssociative();
            
            if ($result) {
                return [
                    'wxpro_num' => $result['WXPRO_NUM'] ?? $result['wxpro_num'],
                    'bidos_num' => $result['BIDOS_NUM'] ?? $result['bidos_num'],
                    'wxpdo_dat' => $result['WXPDO_DAT'] ?? $result['wxpdo_dat']
                ];
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Récupère le nombre d'étapes exemptées pour une proposition
     */
    public function getExemptedStepsCount(int $numeroProposition): int
    {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM wxetp
                    WHERE wxpro_num = (
                        SELECT wxpro_num
                        FROM (
                            SELECT a.wxpro_num
                            FROM wxpdo a, wxpro b
                            WHERE a.wxpro_num = b.wxpro_num
                            AND WFNAT_COD = 'PROP'
                            AND BIDOS_NUM LIKE 'AC/PROP/' || ? || '%'
                            ORDER BY WXPDO_DAT DESC
                        )
                        WHERE rownum = 1
                    )
                    AND WFDEC_COD = '_EXS'";
            
            $count = $this->getConnection()->executeQuery($sql, [$numeroProposition])->fetchOne();
            return (int)$count;
        } catch (\Exception $e) {
            return 0;
        }
    }
}

