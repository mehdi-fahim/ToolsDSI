<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

class EditionBureautiqueOracleService
{
    private Connection $connection;

    public function __construct(Connection $defaultConnection)
    {
        // Utilise la connexion par défaut (Oracle, selon DATABASE_URL)
        $this->connection = $defaultConnection;
    }

    public function fetchEditions(): array
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
    AND ROWNUM <= 100
SQL;
        return $this->connection->fetchAllAssociative($sql);
    }
} 