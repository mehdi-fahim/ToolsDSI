<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;
use ReflectionClass;
use ReflectionMethod;

class AdminDataService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Récupère un enregistrement par son ID
     */
    public function getEntityById(string $entityClass, int $id): ?object
    {
        $repository = $this->entityManager->getRepository($entityClass);
        return $repository->find($id);
    }

    /**
     * Récupère toutes les données d'une entité avec pagination optionnelle
     */
    public function getAllData(string $entityClass, int $page = 1, int $limit = 50): array
    {
        $repository = $this->entityManager->getRepository($entityClass);
        $qb = $repository->createQueryBuilder('e');
        
        $offset = ($page - 1) * $limit;
        
        $qb->setFirstResult($offset)
           ->setMaxResults($limit);
        
        return [
            'data' => $qb->getQuery()->getResult(),
            'total' => $this->getTotalCount($entityClass),
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($this->getTotalCount($entityClass) / $limit)
        ];
    }

    /**
     * Recherche dans une entité avec un terme de recherche
     */
    public function searchData(string $entityClass, string $searchTerm, int $page = 1, int $limit = 50): array
    {
        $repository = $this->entityManager->getRepository($entityClass);
        $qb = $repository->createQueryBuilder('e');
        
        $searchableFields = $this->getSearchableFields($entityClass);
        
        if (!empty($searchableFields)) {
            $orX = $qb->expr()->orX();
            foreach ($searchableFields as $field) {
                $orX->add($qb->expr()->like('e.' . $field, ':searchTerm'));
            }
            $qb->where($orX)
               ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }
        
        $offset = ($page - 1) * $limit;
        $qb->setFirstResult($offset)
           ->setMaxResults($limit);
        
        $results = $qb->getQuery()->getResult();
        $total = $this->getSearchCount($entityClass, $searchTerm, $searchableFields);
        
        return [
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
            'searchTerm' => $searchTerm
        ];
    }

    /**
     * Récupère les métadonnées d'une entité (colonnes, types, etc.)
     */
    public function getEntityMetadata(string $entityClass): array
    {
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $reflection = new ReflectionClass($entityClass);
        
        $columns = [];
        $searchableFields = [];
        
        foreach ($metadata->getFieldNames() as $fieldName) {
            $fieldMapping = $metadata->getFieldMapping($fieldName);
            $getterMethod = 'get' . ucfirst($fieldName);
            
            // Vérifier si le getter existe
            if ($reflection->hasMethod($getterMethod)) {
                $columns[] = [
                    'name' => $fieldName,
                    'label' => $this->formatColumnLabel($fieldName),
                    'type' => $fieldMapping['type'],
                    'nullable' => $fieldMapping['nullable'] ?? false
                ];
                
                // Ajouter aux champs recherchables si c'est un string
                if (in_array($fieldMapping['type'], ['string', 'text'])) {
                    $searchableFields[] = $fieldName;
                }
            }
        }
        
        return [
            'columns' => $columns,
            'searchableFields' => $searchableFields,
            'entityName' => $this->getEntityDisplayName($entityClass),
            'tableName' => $metadata->getTableName()
        ];
    }

    /**
     * Exporte les données au format CSV
     */
    public function exportToCsv(string $entityClass, array $data): string
    {
        $metadata = $this->getEntityMetadata($entityClass);
        $output = fopen('php://temp', 'r+');
        
        // En-têtes
        $headers = array_map(fn($col) => $col['label'], $metadata['columns']);
        fputcsv($output, $headers, ';');
        
        // Données
        foreach ($data as $entity) {
            $row = [];
            foreach ($metadata['columns'] as $column) {
                $getter = 'get' . ucfirst($column['name']);
                if (method_exists($entity, $getter)) {
                    $value = $entity->$getter();
                    $row[] = $this->formatValueForExport($value, $column['type']);
                } else {
                    $row[] = '';
                }
            }
            fputcsv($output, $row, ';');
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Exporte les données au format JSON
     */
    public function exportToJson(string $entityClass, array $data): string
    {
        $metadata = $this->getEntityMetadata($entityClass);
        $exportData = [];
        
        foreach ($data as $entity) {
            $row = [];
            foreach ($metadata['columns'] as $column) {
                $getter = 'get' . ucfirst($column['name']);
                if (method_exists($entity, $getter)) {
                    $value = $entity->$getter();
                    $row[$column['name']] = $this->formatValueForExport($value, $column['type']);
                }
            }
            $exportData[] = $row;
        }
        
        return json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Récupère le nombre total d'enregistrements
     */
    private function getTotalCount(string $entityClass): int
    {
        $repository = $this->entityManager->getRepository($entityClass);
        return $repository->count([]);
    }

    /**
     * Récupère le nombre total d'enregistrements pour une recherche
     */
    private function getSearchCount(string $entityClass, string $searchTerm, array $searchableFields): int
    {
        if (empty($searchableFields)) {
            return $this->getTotalCount($entityClass);
        }
        
        $repository = $this->entityManager->getRepository($entityClass);
        $qb = $repository->createQueryBuilder('e');
        $qb->select('COUNT(e.id)');
        
        $orX = $qb->expr()->orX();
        foreach ($searchableFields as $field) {
            $orX->add($qb->expr()->like('e.' . $field, ':searchTerm'));
        }
        $qb->where($orX)
           ->setParameter('searchTerm', '%' . $searchTerm . '%');
        
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les champs recherchables d'une entité
     */
    private function getSearchableFields(string $entityClass): array
    {
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $searchableFields = [];
        
        foreach ($metadata->getFieldNames() as $fieldName) {
            $fieldMapping = $metadata->getFieldMapping($fieldName);
            if (in_array($fieldMapping['type'], ['string', 'text'])) {
                $searchableFields[] = $fieldName;
            }
        }
        
        return $searchableFields;
    }

    /**
     * Formate le label d'une colonne
     */
    private function formatColumnLabel(string $fieldName): string
    {
        return ucwords(str_replace('_', ' ', $fieldName));
    }

    /**
     * Formate le nom d'affichage de l'entité
     */
    private function getEntityDisplayName(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);
        return end($parts);
    }

    /**
     * Formate une valeur pour l'export
     */
    private function formatValueForExport($value, string $type): string
    {
        if ($value === null) {
            return '';
        }
        
        return match($type) {
            'datetime' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value,
            'date' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value,
            'boolean' => $value ? 'Oui' : 'Non',
            default => (string) $value
        };
    }
} 