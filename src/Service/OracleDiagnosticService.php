<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class OracleDiagnosticService
{
    public function __construct(private Connection $defaultConnection)
    {
    }

    /**
     * Teste la connexion Oracle et retourne les informations de diagnostic
     */
    public function testConnection(): array
    {
        $diagnostics = [
            'php_version' => PHP_VERSION,
            'oci8_loaded' => extension_loaded('oci8'),
            'oci8_version' => extension_loaded('oci8') ? phpversion('oci8') : 'Non disponible',
            'connection_status' => 'Unknown',
            'error_message' => null,
            'test_query_result' => null
        ];

        try {
            // Test de connexion simple
            $result = $this->defaultConnection->fetchOne("SELECT 1 FROM DUAL");
            $diagnostics['connection_status'] = 'Success';
            $diagnostics['test_query_result'] = $result;
        } catch (\Exception $e) {
            $diagnostics['connection_status'] = 'Failed';
            $diagnostics['error_message'] = $e->getMessage();
        }

        return $diagnostics;
    }

    /**
     * Teste les constantes OCI8
     */
    public function testOci8Constants(): array
    {
        $constants = [
            'OCI_NO_AUTO_COMMIT' => defined('OCI_NO_AUTO_COMMIT') ? OCI_NO_AUTO_COMMIT : 'Non définie',
            'OCI_DEFAULT' => defined('OCI_DEFAULT') ? OCI_DEFAULT : 'Non définie',
            'OCI_COMMIT_ON_SUCCESS' => defined('OCI_COMMIT_ON_SUCCESS') ? OCI_COMMIT_ON_SUCCESS : 'Non définie',
        ];

        return $constants;
    }
}
