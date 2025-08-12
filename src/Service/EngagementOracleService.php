<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class EngagementOracleService
{
    private Connection $connection;

    public function __construct(Connection $defaultConnection)
    {
        $this->connection = $defaultConnection;
    }

    /**
     * Récupère les informations d'un engagement
     */
    public function getEngagementInfo(int $exercice, int $numeroEngagement, string $societe): array
    {
        try {
            // Récupérer le type d'engagement
            $typeEngagement = $this->connection->executeQuery(
                "SELECT TATEN_COD FROM TAENG WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            // Récupérer l'ESO administratif
            $esoAdministratif = $this->connection->executeQuery(
                "SELECT TOESO_COD FROM TAENG WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            // Récupérer le responsable de l'engagement
            $responsableEngagement = $this->connection->executeQuery(
                "SELECT TOTIE_COD FROM TAENR WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            // Récupérer le marché rattaché
            $marcheRattache = $this->connection->executeQuery(
                "SELECT TAMAC_NUM FROM TAENG WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            // Récupérer le lot rattaché (requête plus complexe)
            $lotRattache = $this->connection->executeQuery(
                "SELECT TAMAL_REF FROM TAMAL a, TAENG b 
                 WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ? 
                 AND b.tamac_num = a.tamac_num AND b.tamal_cod = a.tamal_cod 
                 AND tavma_numver = (
                     SELECT MAX(tavma_numver) FROM tamal 
                     WHERE tamac_num = a.tamac_num AND tamal_cod = a.tamal_cod
                 )",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            // Vérifier si l'engagement est pluriannuel
            $isPluriannuel = $this->connection->executeQuery(
                "SELECT CASE WHEN TAENG_TEMPLURI = 'F' THEN 0 ELSE 1 END FROM TAENG WHERE ICEXE_NUM = ? AND TAENG_NUM = ?",
                [$exercice, $numeroEngagement]
            )->fetchOne();

            return [
                'type_engagement' => $typeEngagement ?: '',
                'eso_administratif' => $esoAdministratif ?: '',
                'responsable_engagement' => $responsableEngagement ?: '',
                'marche_rattache' => $marcheRattache ?: '',
                'lot_reference' => $lotRattache ?: '',
                'is_pluriannuel' => (bool)$isPluriannuel,
                'found' => true
            ];

        } catch (\Exception $e) {
            return [
                'type_engagement' => '',
                'eso_administratif' => '',
                'responsable_engagement' => '',
                'marche_rattache' => '',
                'lot_reference' => '',
                'is_pluriannuel' => false,
                'found' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si un engagement existe
     */
    public function checkEngagementExists(int $exercice, int $numeroEngagement, string $societe): bool
    {
        try {
            $result = $this->connection->executeQuery(
                "SELECT COUNT(*) FROM TAENG WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            return (int)$result > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
