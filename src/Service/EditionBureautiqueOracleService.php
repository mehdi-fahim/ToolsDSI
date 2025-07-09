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
	nombre AS Nombre_UTILISATION,
	annee AS ANNEE,
	code_BI AS NOM_BI,
	LIB_BI AS DESCRIPTION_BI,
	(
	SELECT
		max(bb.mguti_cod)
	FROM
		bided aa
	INNER JOIN biedi bb ON
		bb.biedi_num = aa.biedi_num
		AND bb.biedi_dxp = (
		SELECT
			max(ee.biedi_dxp)
		FROM
			bided cc
		INNER JOIN biedi ee ON
			ee.biedi_num = cc.biedi_num
			AND to_char(ee.BIEDI_DXP, 'YYYY') = annee
		WHERE
			cc.bidtp_num = aa.bidtp_num)
	WHERE
		aa.bidtp_num = code_BI ) UTILISATEUR,
	(
	SELECT
		max(ee.biedi_dxp)
	FROM
		bided cc
	INNER JOIN biedi ee ON
		ee.biedi_num = cc.biedi_num
		AND to_char(ee.BIEDI_DXP, 'YYYY') = annee
	WHERE
		cc.bidtp_num = CODE_BI) DERNIERE_UTILISATION
FROM
	(
	SELECT
		count(*) nombre ,
		to_char(BIEDI_DXP, 'YYYY') annee,
		bided.bidtp_num CODE_BI ,
		bidtp_lib LIB_BI
	FROM
		biedi
	INNER JOIN bided
ON
		biedi.biedi_num = bided.biedi_num
	INNER JOIN bidtp
ON
		bidtp.bidtp_num = bided.bidtp_num
	GROUP BY
		bided.bidtp_num,
		bidtp_lib,
		to_char(BIEDI_DXP, 'YYYY')
	ORDER BY
		2 DESC,
		1 DESC);
SQL;
        return $this->connection->fetchAllAssociative($sql);
    }
} 