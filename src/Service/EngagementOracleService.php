<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class EngagementOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    /**
     * Récupère les informations d'un engagement
     */
    public function getEngagementInfo(int $exercice, int $numeroEngagement, string $societe): array
    {
        try {
            if (!$this->checkEngagementExists($exercice, $numeroEngagement, $societe)) {
                return [
                    'type_engagement' => '',
                    'eso_administratif' => '',
                    'responsable_engagement' => '',
                    'marche_rattache' => '',
                    'lot_reference' => '',
                    'is_pluriannuel' => false,
                    'found' => false,
                ];
            }

            // Récupérer le type d'engagement
            $typeEngagement = $this->getConnection()->executeQuery(
                "SELECT TATEN_COD FROM TAENG WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            // Récupérer l'ESO administratif
            $esoAdministratif = $this->getConnection()->executeQuery(
                "SELECT TOESO_COD FROM TAENG WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            // Récupérer le responsable de l'engagement (rôle RESEN, exercice courant ou pluriannuel)
            $responsableEngagement = $this->getResponsableEngagement($exercice, $numeroEngagement, $societe) ?? '';

            // Récupérer le marché rattaché
            $marcheRattache = $this->getConnection()->executeQuery(
                "SELECT TAMAC_NUM FROM TAENG WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            // Récupérer le lot rattaché (requête plus complexe)
            $lotRattache = $this->getConnection()->executeQuery(
                "SELECT TAMAL_REF FROM TAMAL a, TAENG b 
                 WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ? 
                 AND b.tamac_num = a.tamac_num AND b.tamal_cod = a.tamal_cod 
                 AND tavma_numver = (
                     SELECT MAX(tavma_numver) FROM tamal 
                     WHERE tamac_num = a.tamac_num AND tamal_cod = a.tamal_cod
                 )",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            // Vérifier si l'engagement est pluriannuel (pour cet exercice et cette société)
            $isPluriannuel = $this->getConnection()->executeQuery(
                "SELECT CASE WHEN TAENG_TEMPLURI = 'F' THEN 0 ELSE 1 END
                 FROM TAENG
                 WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                [$exercice, $numeroEngagement, $societe]
            )->fetchOne();

            return [
                'type_engagement' => $typeEngagement ?: '',
                'eso_administratif' => $esoAdministratif ?: '',
                'responsable_engagement' => $responsableEngagement,
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
            $result = $this->getConnection()->executeQuery(
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
            if (!$this->checkEngagementExists($exercice, $numeroEngagement, $societe)) {
                return [
                    'success' => false,
                    'error' => sprintf(
                        'Aucun engagement trouvé pour l\'exercice %d, le n° %d et la société %s.',
                        $exercice,
                        $numeroEngagement,
                        $societe
                    ),
                ];
            }

            $this->getConnection()->beginTransaction();

            $updated = [];

            // Mise à jour de l'ESO administratif dans TAENG
            if (isset($data['eso_administratif']) && $data['eso_administratif'] !== '') {
                // L'ESO administratif est un champ avec des caractères, pas un numéro de tiers
                $result = $this->getConnection()->executeStatement(
                    "UPDATE TAENG SET TOESO_COD = ? WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                    [$data['eso_administratif'], $exercice, $numeroEngagement, $societe]
                );
                if ($result > 0) {
                    $updated['eso_administratif'] = $data['eso_administratif'];
                }
            }

            // Mise à jour du responsable de l'engagement dans TAENR (rôle RESEN)
            if (isset($data['responsable_engagement']) && $data['responsable_engagement'] !== '') {
                $responsableCod = is_numeric($data['responsable_engagement'])
                    ? (int) $data['responsable_engagement']
                    : $data['responsable_engagement'];

                $currentResponsable = $this->getResponsableEngagement($exercice, $numeroEngagement, $societe);
                $responsableChanged = (string) $currentResponsable !== (string) $data['responsable_engagement'];

                if ($responsableChanged) {
                    $this->assertTiersExists($responsableCod);

                    $result = $this->upsertResponsableEngagement(
                        $exercice,
                        $numeroEngagement,
                        $societe,
                        $responsableCod
                    );

                    if ($result > 0) {
                        $updated['responsable_engagement'] = (string) $data['responsable_engagement'];
                    }
                }
            }

            // Mise à jour du marché rattaché dans TAENG
            if (isset($data['marche_rattache']) && $data['marche_rattache'] !== '') {
                $result = $this->getConnection()->executeStatement(
                    "UPDATE TAENG SET TAMAC_NUM = ? WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                    [$data['marche_rattache'], $exercice, $numeroEngagement, $societe]
                );
                if ($result > 0) {
                    $updated['marche_rattache'] = $data['marche_rattache'];
                }
            }

            // Mise à jour du flag pluriannuel (TAENG_TEMPLURI)
            // La case à cocher renvoie '1' si cochée, rien sinon.
            if (array_key_exists('pluriannuel', $data)) {
                $isPluri = (string) $data['pluriannuel'] === '1';
                // Convention : 'F' = non pluriannuel, 'T' = pluriannuel
                $templuri = $isPluri ? 'T' : 'F';

                $result = $this->getConnection()->executeStatement(
                    "UPDATE TAENG SET TAENG_TEMPLURI = ? WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ?",
                    [$templuri, $exercice, $numeroEngagement, $societe]
                );

                if ($result > 0) {
                    $updated['pluriannuel'] = $templuri;
                }
            }

            $this->getConnection()->commit();

            return [
                'success' => true,
                'updated' => $updated,
                'message' => count($updated) > 0 ? 'Engagement mis à jour avec succès.' : 'Aucune modification effectuée.'
            ];

        } catch (\Exception $e) {
            $this->getConnection()->rollBack();
            return [
                'success' => false,
                'error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ];
        }
    }

    private function getResponsableEngagement(int $exercice, int $numeroEngagement, string $societe): ?string
    {
        $responsable = $this->getConnection()->executeQuery(
            "SELECT TOTIE_COD FROM TAENR
             WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ? AND TAITL_COD = 'RESEN'",
            [$exercice, $numeroEngagement, $societe]
        )->fetchOne();

        if ($responsable !== false && $responsable !== null && $responsable !== '') {
            return (string) $responsable;
        }

        // Engagement pluriannuel : responsable parfois rattaché sur un autre exercice
        $responsableAutreExercice = $this->getConnection()->executeQuery(
            "SELECT TOTIE_COD FROM TAENR
             WHERE TAENG_NUM = ? AND TOTIE_CODSCTE = ? AND TAITL_COD = 'RESEN'
             ORDER BY ICEXE_NUM DESC",
            [$numeroEngagement, $societe]
        )->fetchOne();

        return ($responsableAutreExercice !== false && $responsableAutreExercice !== null && $responsableAutreExercice !== '')
            ? (string) $responsableAutreExercice
            : null;
    }

    private function assertTiersExists(int|string $tiersCod): void
    {
        $params = [$tiersCod];
        if (is_numeric($tiersCod)) {
            $params = [(int) $tiersCod];
        }

        $tiersExists = $this->getConnection()->executeQuery(
            'SELECT COUNT(*) FROM TOTIE WHERE TOTIE_COD = ?',
            $params
        )->fetchOne();

        if ((int) $tiersExists === 0) {
            throw new \Exception("Le numéro de tiers du responsable d'engagement n'existe pas.");
        }
    }

    /**
     * Met à jour ou crée le responsable RESEN sans violer la contrainte TAENRP1.
     */
    private function upsertResponsableEngagement(
        int $exercice,
        int $numeroEngagement,
        string $societe,
        int|string $responsableCod
    ): int {
        $existingResen = (int) $this->getConnection()->executeQuery(
            "SELECT COUNT(*) FROM TAENR
             WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ? AND TAITL_COD = 'RESEN'",
            [$exercice, $numeroEngagement, $societe]
        )->fetchOne();

        if ($existingResen > 0) {
            return $this->getConnection()->executeStatement(
                "UPDATE TAENR SET TOTIE_COD = ?
                 WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ? AND TAITL_COD = 'RESEN'",
                [$responsableCod, $exercice, $numeroEngagement, $societe]
            );
        }

        try {
            return $this->getConnection()->executeStatement(
                "INSERT INTO TAENR (ICEXE_NUM, TAENG_NUM, TOTIE_CODSCTE, TOTIE_COD, TAITL_COD)
                 VALUES (?, ?, ?, ?, 'RESEN')",
                [$exercice, $numeroEngagement, $societe, $responsableCod]
            );
        } catch (\Exception $e) {
            // Enregistrement RESEN déjà présent (clé TAENRP1) : basculer en mise à jour
            if (!str_contains($e->getMessage(), 'ORA-00001')) {
                throw $e;
            }

            $result = $this->getConnection()->executeStatement(
                "UPDATE TAENR SET TOTIE_COD = ?
                 WHERE ICEXE_NUM = ? AND TAENG_NUM = ? AND TOTIE_CODSCTE = ? AND TAITL_COD = 'RESEN'",
                [$responsableCod, $exercice, $numeroEngagement, $societe]
            );

            if ($result > 0) {
                return $result;
            }

            // Cas pluriannuel : le responsable peut exister sur un autre exercice pour le même n°
            return $this->getConnection()->executeStatement(
                "UPDATE TAENR SET TOTIE_COD = ?
                 WHERE TAENG_NUM = ? AND TOTIE_CODSCTE = ? AND TAITL_COD = 'RESEN'",
                [$responsableCod, $numeroEngagement, $societe]
            );
        }
    }
}
