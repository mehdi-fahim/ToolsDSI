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

    /**
     * Met à jour les informations d'un engagement
     */
    public function updateEngagement(int $exercice, int $numeroEngagement, string $societe, array $data): array
    {
        try {
            $this->connection->beginTransaction();

            $updated = [];

            // Mise à jour de l'ESO administratif dans TAENG
            if (isset($data['eso_administratif']) && $data['eso_administratif'] !== '') {
                // L'ESO administratif est un champ avec des caractères, pas un numéro de tiers
                $result = $this->connection->executeStatement(
                    "UPDATE TAENG SET TOESO_COD = ? WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                    [$data['eso_administratif'], $exercice, $numeroEngagement, $societe]
                );
                if ($result > 0) {
                    $updated['eso_administratif'] = $data['eso_administratif'];
                }
            }

            // Mise à jour du responsable de l'engagement dans TAENR
            if (isset($data['responsable_engagement']) && $data['responsable_engagement'] !== '') {
                // Vérifier d'abord que le tiers responsable existe dans la table des tiers
                try {
                    $tiersExists = $this->connection->executeQuery(
                        "SELECT COUNT(*) FROM TOTIE WHERE TOTIE_COD = ?",
                        [$data['responsable_engagement']]
                    )->fetchOne();

                    if ((int)$tiersExists === 0) {
                        throw new \Exception("Le numéro de tiers du responsable d'engagement n'existe pas.");
                    }
                } catch (\Exception $e) {
                    // Si la requête échoue, essayer avec une conversion numérique
                    if (is_numeric($data['responsable_engagement'])) {
                        $tiersExists = $this->connection->executeQuery(
                            "SELECT COUNT(*) FROM TOTIE WHERE TOTIE_COD = ?",
                            [(int)$data['responsable_engagement']]
                        )->fetchOne();

                        if ((int)$tiersExists === 0) {
                            throw new \Exception("Le numéro de tiers du responsable d'engagement n'existe pas.");
                        }
                    } else {
                        throw new \Exception("Le numéro de tiers du responsable d'engagement n'est pas valide.");
                    }
                }

                // Vérifier si un enregistrement existe déjà pour cet engagement
                $existingRecord = $this->connection->executeQuery(
                    "SELECT COUNT(*) FROM TAENR WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                    [$exercice, $numeroEngagement, $societe]
                )->fetchOne();

                if ((int)$existingRecord > 0) {
                    // Mettre à jour l'enregistrement existant
                    $result = $this->connection->executeStatement(
                        "UPDATE TAENR SET TOTIE_COD = ? WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                        [is_numeric($data['responsable_engagement']) ? (int)$data['responsable_engagement'] : $data['responsable_engagement'], $exercice, $numeroEngagement, $societe]
                    );
                } else {
                    // Créer un nouvel enregistrement
                    $result = $this->connection->executeStatement(
                        "INSERT INTO TAENR (ICEXE_NUM, TAENG_NUM, TOTIE_CODSCTE, TOTIE_COD) VALUES (?, ?, ?, ?)",
                        [$exercice, $numeroEngagement, $societe, is_numeric($data['responsable_engagement']) ? (int)$data['responsable_engagement'] : $data['responsable_engagement']]
                    );
                }
                
                if ($result > 0) {
                    $updated['responsable_engagement'] = $data['responsable_engagement'];
                }
            }

            // Mise à jour du marché rattaché dans TAENG
            if (isset($data['marche_rattache']) && $data['marche_rattache'] !== '') {
                $result = $this->connection->executeStatement(
                    "UPDATE TAENG SET TAMAC_NUM = ? WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                    [$data['marche_rattache'], $exercice, $numeroEngagement, $societe]
                );
                if ($result > 0) {
                    $updated['marche_rattache'] = $data['marche_rattache'];
                }
            }

            $this->connection->commit();

            return [
                'success' => true,
                'updated' => $updated,
                'message' => count($updated) > 0 ? 'Engagement mis à jour avec succès.' : 'Aucune modification effectuée.'
            ];

        } catch (\Exception $e) {
            $this->connection->rollBack();
            return [
                'success' => false,
                'error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ];
        }
    }
}
