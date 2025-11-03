<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class PlansOfficeOracleService
{
    public function __construct(private Connection $etudesConnection)
    {
    }

    public function triggerGeneration(string $username): void
    {
        // Equivalent à: insert into TRAITEMENT_EXPLOITATION values ('PLAN', :user, '', sysdate)
        $sql = "INSERT INTO TRAITEMENT_EXPLOITATION (TRAITEMENT, UTILISATEUR, COMMENTAIRE, DATE_CRE) VALUES ('PLAN', :user, '', SYSDATE)";
        $this->etudesConnection->executeStatement($sql, ['user' => $username]);
    }

    public function exportPlansCsv(): string
    {
        $sql = "SELECT PAESI_CODEXT, TOESO_COD, TOESO_LIB, TOEAD_NUMTEL, GLFAC_REF, TOTIE_CODSOC, CAINT_NUM, CACTR_NUM, CACTR_VRS, "
            . "ADRESSE1, ADRESSE2, ADRESSE3, GLREC_TITRE, GLREC_INTCOU1, PARTI, GLNAC_COD, CXPLA_NUM, BIPCD_LIB, DETTE, OD, TYPE_L, CAT, CAREC_MNTRTOT, SOLDE, CHRONO, TITRE_LIB, "
            . "to_char(DATE1,'dd/mm/yyyy') DATE1, replace(to_char(MNT1, '990.00'), '.', ',') MNT1,  "
            . "to_char(DATE2,'dd/mm/yyyy') DATE2,  replace(To_Char(MNT2, '990.00'), '.', ',') MNT2, "
            . "to_char(DATE3,'dd/mm/yyyy') DATE3,  replace(To_Char(MNT3, '990.00'), '.', ',') MNT3, "
            . "to_char(DATE4,'dd/mm/yyyy') DATE4,  replace(To_Char(MNT4, '990.00'), '.', ',') MNT4, "
            . "to_char(DATE5,'dd/mm/yyyy') DATE5,  replace(To_Char(MNT5, '990.00'), '.', ',') MNT5, "
            . "to_char(DATE6,'dd/mm/yyyy') DATE6,  replace(To_Char(MNT6, '990.00'), '.', ',') MNT6, "
            . "to_char(DATE7,'dd/mm/yyyy') DATE7,  replace(To_Char(MNT7, '990.00'), '.', ',') MNT7, "
            . "to_char(DATE8,'dd/mm/yyyy') DATE8,  replace(To_Char(MNT8, '990.00'), '.', ',') MNT8, "
            . "to_char(DATE9,'dd/mm/yyyy') DATE9,  replace(To_Char(MNT9, '990.00'), '.', ',') MNT9, "
            . "to_char(DATE10,'dd/mm/yyyy') DATE10,  replace(To_Char(MNT10, '990.00'), '.', ',') MNT10, "
            . "to_char(DATE11,'dd/mm/yyyy') DATE11,  replace(To_Char(MNT11, '990.00'), '.', ',') MNT11, "
            . "to_char(DATE12,'dd/mm/yyyy') DATE12,  replace(To_Char(MNT12, '990.00'), '.', ',') MNT12, "
            . "to_char(DATE_DETTE,'dd/mm/yyyy')  DATE_DETTE, "
            . "LIBELLE_REGUL, RESTE_A_PAYER, CGLS, PRELEVE "
            . "FROM PLAN.PLAN_CORTEX";

        $rows = $this->etudesConnection->executeQuery($sql)->fetchAllAssociative();

        $output = fopen('php://temp', 'r+');
        if (!empty($rows)) {
            // headers
            fputcsv($output, array_keys($rows[0]), ';');
            foreach ($rows as $row) {
                fputcsv($output, array_map(static fn($v) => $v === null ? '' : $v, $row), ';');
            }
        } else {
            // empty set: still output headers per spec
            $headers = [
                'PAESI_CODEXT','TOESO_COD','TOESO_LIB','TOEAD_NUMTEL','GLFAC_REF','TOTIE_CODSOC','CAINT_NUM','CACTR_NUM','CACTR_VRS',
                'ADRESSE1','ADRESSE2','ADRESSE3','GLREC_TITRE','GLREC_INTCOU1','PARTI','GLNAC_COD','CXPLA_NUM','BIPCD_LIB','DETTE','OD','TYPE_L','CAT','CAREC_MNTRTOT','SOLDE','CHRONO','TITRE_LIB',
                'DATE1','MNT1','DATE2','MNT2','DATE3','MNT3','DATE4','MNT4','DATE5','MNT5','DATE6','MNT6','DATE7','MNT7','DATE8','MNT8','DATE9','MNT9','DATE10','MNT10','DATE11','MNT11','DATE12','MNT12','DATE_DETTE',
                'LIBELLE_REGUL','RESTE_A_PAYER','CGLS','PRELEVE'
            ];
            fputcsv($output, $headers, ';');
        }
        rewind($output);
        return stream_get_contents($output) ?: '';
    }
}


