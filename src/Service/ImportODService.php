<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ImportODService
{
    private string $tempDir;
    private SessionInterface $session;
    private ImportODOracleService $oracleService;

    public function __construct(SessionInterface $session, string $projectDir, ImportODOracleService $oracleService)
    {
        $this->session = $session;
        $this->oracleService = $oracleService;
        $this->tempDir = $projectDir . '/var/temp/import_od';
        
        // Créer le répertoire temp s'il n'existe pas
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Sauvegarde le fichier CSV uploadé et le parse
     */
    public function uploadAndParseCsv(UploadedFile $file): array
    {
        $sessionId = $this->session->getId();
        $filename = $sessionId . '_' . time() . '.csv';
        $filePath = $this->tempDir . '/' . $filename;
        
        // Sauvegarder le fichier
        $file->move($this->tempDir, $filename);
        
        // Parser le CSV
        $data = $this->parseCsvFile($filePath);
        
        // Sauvegarder les données en session
        $this->session->set('import_od_file', $filePath);
        $this->session->set('import_od_data', $data);
        
        return $data;
    }

    /**
     * Parse un fichier CSV et retourne les données
     */
    private function parseCsvFile(string $filePath): array
    {
        $data = [];
        $headers = [];
        
        if (($handle = fopen($filePath, 'r')) !== false) {
            $rowIndex = 0;
            
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if ($rowIndex === 0) {
                    // Première ligne = en-têtes
                    $headers = array_map('trim', $row);
                } else {
                    // Lignes de données
                    $rowData = [];
                    for ($i = 0; $i < count($headers); $i++) {
                        $rowData[$headers[$i]] = isset($row[$i]) ? trim($row[$i]) : '';
                    }
                    $data[] = $rowData;
                }
                $rowIndex++;
            }
            fclose($handle);
        }
        
        return [
            'headers' => $headers,
            'data' => $data,
            'total_rows' => count($data)
        ];
    }

    /**
     * Récupère les données CSV depuis la session
     */
    public function getCsvData(): ?array
    {
        return $this->session->get('import_od_data');
    }

    /**
     * Valide les données CSV selon les colonnes attendues
     */
    public function validateCsvData(array $csvData): array
    {
        $errors = [];
        $expectedColumns = [
            'PAESI_CODEXT',
            'PAESI_LIBELLE',
            'PAESI_CODE',
            'PAESI_TYPE',
            'PAESI_ACTIF',
            'PAESI_ORDRE',
            'PAESI_COMMENTAIRE'
        ];
        
        // Vérifier les colonnes
        $missingColumns = array_diff($expectedColumns, $csvData['headers']);
        if (!empty($missingColumns)) {
            $errors[] = 'Colonnes manquantes: ' . implode(', ', $missingColumns);
        }
        
        // Vérifier les données
        foreach ($csvData['data'] as $index => $row) {
            $rowNumber = $index + 2; // +2 car on commence à la ligne 2 (après les en-têtes)
            
            // Vérifier que PAESI_CODEXT n'est pas vide
            if (empty($row['PAESI_CODEXT'])) {
                $errors[] = "Ligne $rowNumber: PAESI_CODEXT est obligatoire";
            }
            
            // Vérifier que PAESI_LIBELLE n'est pas vide
            if (empty($row['PAESI_LIBELLE'])) {
                $errors[] = "Ligne $rowNumber: PAESI_LIBELLE est obligatoire";
            }
        }
        
        return $errors;
    }

    /**
     * Intègre les données CSV dans la base Oracle
     */
    public function integrateData(string $userId): array
    {
        $csvData = $this->getCsvData();
        if (!$csvData) {
            return ['success' => false, 'error' => 'Aucune donnée à intégrer'];
        }
        
        try {
            // Vider la table OD_A_CHARGER
            $this->oracleService->clearOdACharger();
            
            // Insérer les données dans OD_A_CHARGER
            $insertedCount = $this->oracleService->insertDataToOdACharger($csvData);
            
            // Nettoyer les codes PAESI_CODEXT (supprimer les espaces)
            $this->oracleService->cleanPaesiCodes();
            
            // Valider les données
            $validationErrors = $this->oracleService->validateData();
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'error' => 'Erreurs de validation: ' . implode('; ', $validationErrors)
                ];
            }
            
            // Exécuter la procédure d'intégration
            $integrationResult = $this->oracleService->executeIntegrationProcedure($userId);
            
            if (!$integrationResult['success']) {
                return $integrationResult;
            }
            
            // Nettoyer les fichiers temporaires
            $this->cleanup();
            
            return [
                'success' => true,
                'integrated_count' => $insertedCount,
                'errors' => []
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de l\'intégration: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Nettoie les fichiers temporaires
     */
    public function cleanup(): void
    {
        $filePath = $this->session->get('import_od_file');
        if ($filePath && file_exists($filePath)) {
            unlink($filePath);
        }
        
        $this->session->remove('import_od_file');
        $this->session->remove('import_od_data');
    }

    /**
     * Supprime les espaces des codes PAESI_CODEXT
     */
    public function cleanPaesiCodes(array $csvData): array
    {
        foreach ($csvData['data'] as &$row) {
            if (isset($row['PAESI_CODEXT'])) {
                $row['PAESI_CODEXT'] = str_replace(' ', '', $row['PAESI_CODEXT']);
            }
        }
        
        return $csvData;
    }
}
