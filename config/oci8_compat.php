<?php

/**
 * Compatibilité OCI8 : définit OCI_NO_AUTO_COMMIT si l'extension ne l'expose pas
 * (certaines versions ou builds de l'extension OCI8 sur Windows ne définissent pas cette constante).
 * Valeur 0 = mode session par défaut (équivalent OCI_DEFAULT).
 */
if (! defined('OCI_NO_AUTO_COMMIT')) {
    define('OCI_NO_AUTO_COMMIT', 0);
}
