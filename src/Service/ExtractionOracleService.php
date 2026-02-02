<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class ExtractionOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->getConnection()Resolver->getConnection();
    }

    /**
     * Génère le CSV pour un groupe SI avec les champs sélectionnés
     */
    public function generateCsvForGroup(string $groupeSi, array $selectedFields): string
    {
        $selectedFields = array_values(array_unique($selectedFields));
        $sql = $this->buildExtractionQuery($groupeSi, $selectedFields);
        
        try {
            $data = $this->getConnection()->executeQuery($sql)->fetchAllAssociative();
            
            // Générer le CSV
            $csv = $this->arrayToCsv($data, $selectedFields);
            
            return $csv;
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de la génération du CSV: ' . $e->getMessage());
        }
    }

    /**
     * Construit la requête SQL en fonction des champs sélectionnés
     * Basé sur le code VB fourni
     */
    private function buildExtractionQuery(string $groupeSi, array $selectedFields): string
    {
        // Initialisation des clauses
        $clauseSelect = "SELECT a.PAESI_CODEXT as ESI";
        $clauseFrom = "FROM PAESI a";
        $clauseFrom .= " JOIN SYN_PAT x ON x.paesi_num = a.paesi_num";
        
        // Construction de la clause WHERE selon le groupe
        $clauseWhere = $this->buildWhereClause($groupeSi);
        
        // Ajout des jointures et champs selon les sélections
        $this->addPatrimoineFields($clauseSelect, $clauseFrom, $selectedFields);
        $this->addLocataireFields($clauseSelect, $clauseFrom, $selectedFields);
        $this->addMenageFields($clauseSelect, $selectedFields);
        $this->addLoyerFields($clauseSelect, $selectedFields);
        
        $sql = $clauseSelect . " " . $clauseFrom . " " . $clauseWhere;
        
        return $sql;
    }

    /**
     * Construit la clause WHERE selon le groupe SI
     */
    private function buildWhereClause(string $groupeSi): string
    {
        $groupeUpper = strtoupper(trim($groupeSi));
        
        // Si le groupe est vide, on retourne une clause de base sans filtre de groupe
        if (empty($groupeUpper)) {
            return "WHERE a.panes_cod NOT IN ('GPE', 'BAT', 'ESC') AND PAESI_DATSOR IS NULL ORDER BY a.PAESI_CODEXT";
        }
        
        if (in_array($groupeUpper, ['PCH', 'SUD', 'NORD', 'NORD-EST', 'EST'])) {
            if ($groupeUpper === 'PCH') {
                return "WHERE a.panes_cod NOT IN ('GPE', 'BAT', 'ESC') AND PAESI_DATSOR IS NULL ORDER BY a.PAESI_CODEXT";
            } elseif ($groupeUpper === 'EST') {
                return "WHERE UPPER(x.agence) LIKE '%EST' AND a.panes_cod NOT IN ('GPE', 'BAT', 'ESC') AND PAESI_DATSOR IS NULL ORDER BY a.PAESI_CODEXT";
            } else {
                return "WHERE UPPER(x.agence) LIKE '%{$groupeUpper}' AND a.panes_cod NOT IN ('GPE', 'BAT', 'ESC') AND PAESI_DATSOR IS NULL ORDER BY a.PAESI_CODEXT";
            }
        } else {
            return "WHERE x.groupe = '{$groupeUpper}' AND a.panes_cod NOT IN ('GPE', 'BAT', 'ESC') AND PAESI_DATSOR IS NULL ORDER BY a.PAESI_CODEXT";
        }
    }

    /**
     * Ajoute les champs du patrimoine
     */
    private function addPatrimoineFields(string &$clauseSelect, string &$clauseFrom, array $selectedFields): void
    {
        // Vérifier si on a besoin de PAIFG (Étage ou Typologie)
        if (in_array('etage', $selectedFields) || in_array('typologie', $selectedFields)) {
            $clauseFrom .= " JOIN paifg b ON b.paesi_num = a.paesi_num";
        }
        
        // Étage
        if (in_array('etage', $selectedFields)) {
            $clauseSelect .= ", b.PAETG_COD as Etage";
        }
        
        // Typologie
        if (in_array('typologie', $selectedFields)) {
            $clauseSelect .= ", b.PAETY_COD as Typologie";
        }
        
        // Nature
        if (in_array('nature', $selectedFields)) {
            $clauseSelect .= ", a.Panes_COD as Nature";
        }
        
        // Adresse
        if (in_array('adresse', $selectedFields)) {
            $clauseFrom .= " JOIN PAEAD p ON p.paesi_num = a.paesi_num JOIN mgadr md ON md.mgadr_num = p.mgadr_num";
            $clauseSelect .= ", md.mgadr_numvoi||' '||md.mgtvo_cod||' '||md.mgadr_nomvoi||' '||md.mgadr_libco1||' '||md.mgadr_libco2||' '||md.mgadr_libco3 as Adresse, md.mgadr_codpos as CP, md.mgadr_libcom as Commune";
        }
        
        // Groupe
        if (in_array('groupe', $selectedFields)) {
            $clauseSelect .= ", x.groupe as Groupe";
        }
        
        // Agence
        if (in_array('agence', $selectedFields)) {
            $clauseSelect .= ", GETAGENCEESI(a.PAESI_NUM, 'L') as Agence";
        }
        
        // Statut
        if (in_array('statut', $selectedFields)) {
            $clauseSelect .= ", (SELECT PAESU_LIB FROM paesu WHERE paesu_cod = (SELECT paesu_cod FROM palst WHERE paesi_num = a.paesi_num AND PALST_DTF IS NULL)) as Statut_ESI";
        }
        
        // Surface Habitable
        if (in_array('surface_habitable', $selectedFields)) {
            $clauseSelect .= ", FGETSURFACE(a.paesi_num, 'SH') as Surface_Habitable";
        }
        
        // Surface Utile
        if (in_array('surface_utile', $selectedFields)) {
            $clauseSelect .= ", (SELECT MAX(PAETM_VAL) FROM PAETM WHERE PATAN_COD = 'SU' AND PAESI_NUM = a.PAESI_NUM) AS Surface_utile";
        }
        
        // Numéro DRE (remplace Numéro RPLS)
        if (in_array('numero_rpls', $selectedFields)) {
            $clauseSelect .= ", (SELECT PAEST_VAL FROM PAEST WHERE paesi_num = a.paesi_num AND MGZDE_COD = 'NODRE' AND MGENT_COD = 'PAESI') as Numero_DRE";
        }
        
        // Cat. Financement
        if (in_array('cat_financement', $selectedFields)) {
            $clauseSelect .= ", GET_FINANCEMENT(a.paesi_num) as Cat_Financement";
        }
        
        // Réservataire
        if (in_array('reservataire', $selectedFields)) {
            $clauseSelect .= ", FGETRESERVATAIRE(a.paesi_num) as Réservataire";
        }
    }

    /**
     * Ajoute les champs du locataire
     */
    private function addLocataireFields(string &$clauseSelect, string &$clauseFrom, array $selectedFields): void
    {
        // Vérifier si on a besoin des informations locataire
        $locataireFields = ['contrat', 'intitule', 'tiers', 'libelle_intitule', 'telephone', 'age', 'assurance_jour', 'prelevement', 'date_debut_contrat', 'date_fin_contrat', 'adresse_regroupement', 'immatriculation', 'marque_vehicule'];
        
        if (array_intersect($locataireFields, $selectedFields)) {
            $clauseSelect .= ", c.GLCON_NUM as Contrat, c.GLCON_NUMVER as Version_Contrat";
            $clauseFrom .= " LEFT JOIN GLELC c ON c.paesi_num = a.paesi_num AND (SELECT GLCON_TEMCA FROM GLCON WHERE glcon_num = c.glcon_num AND glcon_numver = c.glcon_numver) = 'F' AND c.GLELC_DTF IS NULL";
            
            // Intitulé
            if (in_array('intitule', $selectedFields)) {
                $clauseSelect .= ", d.CAINT_NUM as Intitule";
                $clauseFrom .= " LEFT JOIN CACTR d ON d.CACTR_NUM = c.GLCON_NUM AND d.CACTR_VRS = c.GLCON_NUMVER";
                $clauseFrom .= " LEFT JOIN GLREC f ON f.CAINT_NUM = d.CAINT_NUM";
            }
            
            // Tiers
            if (in_array('tiers', $selectedFields)) {
                $clauseSelect .= ", e.Totie_COD as Tiers";
                $clauseFrom .= " LEFT JOIN CAICL e ON e.caint_num = d.caint_num AND e.CAICL_TEMP = 'T' AND e.CAICL_DTF IS NULL";
            }
            
            // Libellé intitulé
            if (in_array('libelle_intitule', $selectedFields)) {
                $clauseSelect .= ", f.GLREC_TITRE||' '||f.GLREC_INTCOU1 as Libelle_Intitule";
            }
            
            // Téléphone
            if (in_array('telephone', $selectedFields)) {
                $clauseSelect .= ", GET_PORTABLE(e.Totie_COD) as Tel_Portable";
            }
            
            // Age
            if (in_array('age', $selectedFields)) {
                $clauseSelect .= ", GET_AGE(e.Totie_COD) as Age";
            }
            
            // Assurance à jour
            if (in_array('assurance_jour', $selectedFields)) {
                $clauseSelect .= ", GET_ASSURANCE_A_JOUR(c.GLCON_NUM) as Assurance_A_jour";
            }
            
            // Prélèvement
            if (in_array('prelevement', $selectedFields)) {
                $clauseSelect .= ", DECODE(GET_PRELEVE(d.caint_num), 0, 'NON', 'OUI') as Prelevement";
            }
            
            // Date Début contrat
            if (in_array('date_debut_contrat', $selectedFields)) {
                $clauseSelect .= ", (SELECT TO_CHAR(GLCON_DTD, 'dd/mm/yyyy') FROM GLCON WHERE GLCON_NUM = c.GLCON_NUM AND GLCON_NUMver = c.GLCON_NUMVER) as Date_Debut_Contrat";
            }
            
            // Date Fin contrat
            if (in_array('date_fin_contrat', $selectedFields)) {
                $clauseSelect .= ", (SELECT TO_CHAR(GLCON_DTF, 'dd/mm/yyyy') FROM GLCON WHERE GLCON_NUM = c.GLCON_NUM AND GLCON_NUMver = c.GLCON_NUMVER) as Date_Fin_Contrat";
            }
            
            // Adresse de regroupement
            if (in_array('adresse_regroupement', $selectedFields)) {
                $clauseSelect .= ", (SELECT TRIM(mgadr.mgadr_numvoi||' '||mgadr.mgtvo_cod||' '||mgadr.mgadr_nomvoi||' '||mgadr.mgadr_libco1) FROM mgadr WHERE mgadr_num = f.MGADR_NUM) as Adresse_Regroupement, (SELECT TRIM(MGADR.MGADR_CODPOS) FROM mgadr WHERE mgadr_num = f.MGADR_NUM) as CP_Regroupement, (SELECT TRIM(MGADR.MGADR_LIBCOM) FROM mgadr WHERE mgadr_num = f.MGADR_NUM) as Ville_Regroupement";
            }
            
            // Immatriculation
            if (in_array('immatriculation', $selectedFields)) {
                $clauseSelect .= ", (SELECT GLCZP_VAL FROM GLCZP WHERE (GLCZP.GLCON_NUM = c.GLCON_NUM) AND (GLCZP.GLCON_NUMVER = c.GLCON_NUMVER) AND (GLCZP.MGZDE_COD = 'IMMAT') AND (ROWNUM = 1)) as immatriculation";
            }
            
            // Marque véhicule
            if (in_array('marque_vehicule', $selectedFields)) {
                $clauseSelect .= ", (SELECT GLCZP_VAL FROM GLCZP WHERE (GLCZP.GLCON_NUM = c.GLCON_NUM) AND (GLCZP.GLCON_NUMVER = c.GLCON_NUMVER) AND (GLCZP.MGZDE_COD = 'MARQ') AND (ROWNUM = 1)) as Marque_vehicule";
            }
        }
    }

    /**
     * Ajoute les champs du ménage
     */
    private function addMenageFields(string &$clauseSelect, array $selectedFields): void
    {
        // Nombre d'occupants
        if (in_array('nombre_occupants', $selectedFields)) {
            $clauseSelect .= ", F_GET_NBOCC(c.GLCON_NUM, c.GLCON_NUMVER) as NB_Occupants";
        }
        
        // NB Adultes
        if (in_array('nb_adultes', $selectedFields)) {
            $clauseSelect .= ", F_GET_NBOCC(c.GLCON_NUM, c.GLCON_NUMVER) - GET_NB_ENFANTS(c.GLCON_NUM) as NB_ADULTES";
        }
        
        // NB Enfants
        if (in_array('nb_enfants', $selectedFields)) {
            $clauseSelect .= ", GET_NB_ENFANTS(c.GLCON_NUM) as NB_Enfants";
        }
        
        // Revenu Imposable
        if (in_array('revenu_imposable', $selectedFields)) {
            $clauseSelect .= ", GET_RI(c.GLCON_NUM, c.GLCON_NUMVER) as Revenu_Imposable_Menage";
        }
    }

    /**
     * Ajoute les champs du loyer
     */
    private function addLoyerFields(string &$clauseSelect, array $selectedFields): void
    {
        // Loyer
        if (in_array('loyer', $selectedFields)) {
            $clauseSelect .= ", GET_lOYER(c.GLCON_NUM, c.GLCON_NUMVER) as Loyer_Contrat";
        }
        
        // Charges
        if (in_array('charges', $selectedFields)) {
            $clauseSelect .= ", GET_CHARGES(c.GLCON_NUM, c.GLCON_NUMVER) as Charges_Contrat";
        }
        
        // Dette
        if (in_array('dette', $selectedFields)) {
            $clauseSelect .= ", GET_DETTE(d.CAINT_NUM) as Dette_Intitule";
        }
        
        // APL
        if (in_array('apl', $selectedFields)) {
            $clauseSelect .= ", get_apl(c.GLCON_NUM, c.GLCON_NUMVER) as APL";
        }
        
        // RLS
        if (in_array('rls', $selectedFields)) {
            $clauseSelect .= ", GET_RLS(c.GLCON_NUM, c.GLCON_NUMVER) as RLS";
        }
        
        // Loyer Plafond
        if (in_array('loyer_plafond', $selectedFields)) {
            $clauseSelect .= ", GET_LOYERMAX_CONV(a.paesi_num) as Loyer_Plafond";
        }
    }

    /**
     * Convertit un tableau en CSV
     */
    private function arrayToCsv(array $data, array $selectedFields): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        
        // En-têtes
        $headers = [];
        foreach ($selectedFields as $field) {
            $headers[] = $this->sanitizeLabel($this->getFieldLabel($field));
        }
        fputcsv($output, $headers, ';');

        // Données
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($selectedFields as $field) {
                $csvRow[] = $row[$this->getFieldKey($field)] ?? '';
            }
            fputcsv($output, $csvRow, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Retourne la clé de colonne pour un champ
     */
    private function getFieldKey(string $field): string
    {
        $fieldMap = [
            // Patrimoine
            'esi' => 'ESI',
            'etage' => 'ETAGE',
            'typologie' => 'TYPOLOGIE',
            'nature' => 'NATURE',
            'adresse' => 'ADRESSE',
            'groupe' => 'GROUPE',
            'agence' => 'AGENCE',
            'statut' => 'STATUT_ESI',
            'surface_habitable' => 'SURFACE_HABITABLE',
            'surface_utile' => 'SURFACE_UTILE',
            'numero_rpls' => 'NUMERO_DRE',
            'cat_financement' => 'CAT_FINANCEMENT',
            'reservataire' => 'RÉSERVATAIRE',

            // Locataire
            'contrat' => 'CONTRAT',
            'intitule' => 'INTITULE',
            'tiers' => 'TIERS',
            'libelle_intitule' => 'LIBELLE_INTITULE',
            'telephone' => 'TEL_PORTABLE',
            'age' => 'AGE',
            'assurance_jour' => 'ASSURANCE_A_JOUR',
            'prelevement' => 'PRELEVEMENT',
            'date_debut_contrat' => 'DATE_DEBUT_CONTRAT',
            'date_fin_contrat' => 'DATE_FIN_CONTRAT',
            'adresse_regroupement' => 'ADRESSE_REGROUPEMENT',
            'immatriculation' => 'IMMATRICULATION',
            'marque_vehicule' => 'MARQUE_VEHICULE',

            // Ménage
            'nombre_occupants' => 'NB_OCCUPANTS',
            'nb_adultes' => 'NB_ADULTES',
            'nb_enfants' => 'NB_ENFANTS',
            'revenu_imposable' => 'REVENU_IMPOSABLE_MENAGE',

            // Loyer
            'loyer' => 'LOYER_CONTRAT',
            'charges' => 'CHARGES_CONTRAT',
            'dette' => 'DETTE_INTITULE',
            'apl' => 'APL',
            'rls' => 'RLS',
            'loyer_plafond' => 'LOYER_PLAFOND',
        ];

        return $fieldMap[$field] ?? strtoupper($field);
    }

    /**
     * Retourne le label français d'un champ
     */
    private function getFieldLabel(string $field): string
    {
        $labels = [
            // Patrimoine
            'esi' => 'ESI',
            'etage' => 'Étage',
            'typologie' => 'Typologie',
            'nature' => 'Nature',
            'adresse' => 'Adresse',
            'groupe' => 'Groupe',
            'agence' => 'Agence',
            'statut' => 'Statut',
            'surface_habitable' => 'Surface Habitable',
            'surface_utile' => 'Surface Utile',
            'numero_rpls' => 'Numéro DRE',
            'cat_financement' => 'Cat. Financement',
            'reservataire' => 'Réservataire',

            // Locataire
            'contrat' => 'Contrat',
            'intitule' => 'Intitulé',
            'tiers' => 'Tiers',
            'libelle_intitule' => 'Libellé Intitulé',
            'telephone' => 'Téléphone',
            'age' => 'Age',
            'assurance_jour' => 'Assurance à jour',
            'prelevement' => 'Prélèvement',
            'date_debut_contrat' => 'Date Début Contrat',
            'date_fin_contrat' => 'Date Fin Contrat',
            'adresse_regroupement' => 'Adresse de regroupement',
            'immatriculation' => 'Immatriculation',
            'marque_vehicule' => 'Marque Véhicule',

            // Ménage
            'nombre_occupants' => 'Nombre d\'occupants',
            'nb_adultes' => 'NB. Adultes',
            'nb_enfants' => 'NB. Enfants',
            'revenu_imposable' => 'Revenu Imposable',

            // Loyer
            'loyer' => 'Loyer',
            'charges' => 'Charges',
            'dette' => 'Dette',
            'apl' => 'APL',
            'rls' => 'RLS',
            'loyer_plafond' => 'Loyer Plafond',
        ];

        return $labels[$field] ?? ucfirst($field);
    }

    /**
     * Retourne la requête SQL pour affichage (admin seulement)
     */
    public function getQueryForDisplay(string $groupeSi, array $selectedFields): string
    {
        return $this->buildExtractionQuery($groupeSi, array_values(array_unique($selectedFields)));
    }

    /**
     * Supprime les accents et normalise les libellés pour l'export CSV
     */
    private function sanitizeLabel(string $label): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if ($normalized === false) {
            $normalized = $label;
        }

        $normalized = preg_replace('/\s+/', ' ', $normalized ?? $label);

        return trim($normalized ?? $label);
    }
} 