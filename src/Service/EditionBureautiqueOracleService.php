<?php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class EditionBureautiqueOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    public function fetchEditions(string $search = '', int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        // Requête de base
        $baseSql = <<<SQL
FROM
    BIDTP b,
    ICTRS i 
WHERE 
    i.ICTRS_NOM = b.BIDTP_NUM
SQL;

        // Conditions de recherche
        $params = [];
        $whereConditions = [];

        if (!empty($search)) {
            $whereConditions[] = "UPPER(b.BIDTP_LIB) LIKE UPPER(:search)";
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($whereConditions)) {
            $baseSql .= ' AND ' . implode(' AND ', $whereConditions);
        }

        // Requête pour le total
        $countSql = "SELECT COUNT(*) as total " . $baseSql;
        $total = $this->getConnection()
            ->executeQuery($countSql, $params)
            ->fetchOne();

        // Requête principale avec pagination
        $sql = <<<SQL
SELECT
    b.BIDTP_NUM AS NOM_BI,
    CASE
        WHEN b.BIODO_PRG IS NULL THEN 'Non renseigné'
        WHEN b.BIODO_PRG = 'WORDUNG'  THEN 'Document Word (.doc)'
        WHEN b.BIODO_PRG = 'EXCELUNG' THEN 'Document Excel (.xls)'
        WHEN b.BIODO_PRG = 'XLSXUNG'  THEN 'Document Excel (.xlsx)'
        WHEN b.BIODO_PRG = 'WRDNGPDF' THEN 'Document PDF (.pdf)'
        ELSE 'Type inconnu'
    END AS DOCUMENT_TYPE,
    b.BIDTP_LIB AS DESCRIPTION_BI,
    b.BIDTP_NOM AS NOM_DOCUMENT,
    CASE 
        WHEN i.ICTRS_DSC IS NULL THEN 'Pas de description'
        ELSE DBMS_LOB.SUBSTR(i.ICTRS_DSC, 4100)
    END AS DESCRIPTION_PLUS
$baseSql
ORDER BY b.BIDTP_NUM
OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
SQL;

        // Ajout pagination dans les paramètres
        $params['offset'] = $offset;
        $params['limit'] = $limit;

        // Types nécessaires pour Oracle
        $types = [
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];

        $data = $this->getConnection()
            ->executeQuery($sql, $params, $types)
            ->fetchAllAssociative();

        return [
            'data' => $data,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
        ];
    }

    public function fetchEditionById(string $nomBi): ?array
    {
        $sql = <<<SQL
SELECT
    b.BIDTP_NUM AS NOM_BI,
    CASE
        WHEN b.BIODO_PRG IS NULL THEN 'Non renseigné'
        WHEN b.BIODO_PRG = 'WORDUNG'  THEN 'Document Word (.doc)'
        WHEN b.BIODO_PRG = 'EXCELUNG' THEN 'Document Excel (.xls)'
        WHEN b.BIODO_PRG = 'XLSXUNG'  THEN 'Document Excel (.xlsx)'
        WHEN b.BIODO_PRG = 'WRDNGPDF' THEN 'Document PDF (.pdf)'
        ELSE 'Type inconnu'
    END AS DOCUMENT_TYPE,
    b.BIDTP_LIB AS DESCRIPTION_BI,
    b.BIDTP_NOM AS NOM_DOCUMENT,
    CASE 
        WHEN i.ICTRS_DSC IS NULL THEN 'Pas de description'
        ELSE DBMS_LOB.SUBSTR(i.ICTRS_DSC, 4100)
    END AS DESCRIPTION_PLUS
FROM
    BIDTP b,
    ICTRS i 
WHERE 
    i.ICTRS_NOM = b.BIDTP_NUM
    AND b.BIDTP_NUM = :nomBi
SQL;

        $result = $this->getConnection()
            ->executeQuery($sql, ['nomBi' => $nomBi])
            ->fetchAssociative();

        return $result ?: null;
    }

    public function getQueryForEdition(string $nomBi): string
    {
        return <<<SQL
SELECT
    b.BIDTP_NUM AS NOM_BI,
    CASE
        WHEN b.BIODO_PRG IS NULL THEN 'Non renseigné'
        WHEN b.BIODO_PRG = 'WORDUNG'  THEN 'Document Word (.doc)'
        WHEN b.BIODO_PRG = 'EXCELUNG' THEN 'Document Excel (.xls)'
        WHEN b.BIODO_PRG = 'XLSXUNG'  THEN 'Document Excel (.xlsx)'
        WHEN b.BIODO_PRG = 'WRDNGPDF' THEN 'Document PDF (.pdf)'
        ELSE 'Type inconnu'
    END AS DOCUMENT_TYPE,
    b.BIDTP_LIB AS DESCRIPTION_BI,
    b.BIDTP_NOM AS NOM_DOCUMENT,
    CASE 
        WHEN i.ICTRS_DSC IS NULL THEN 'Pas de description'
        ELSE DBMS_LOB.SUBSTR(i.ICTRS_DSC, 4100)
    END AS DESCRIPTION_PLUS
FROM
    BIDTP b,
    ICTRS i 
WHERE 
    i.ICTRS_NOM = b.BIDTP_NUM
    AND b.BIDTP_NUM = '{$nomBi}'
ORDER BY b.BIDTP_NUM
SQL;
    }
}
