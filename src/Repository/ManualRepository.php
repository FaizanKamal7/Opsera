<?php

namespace App\Repository;

use App\Entity\Manual;
use App\Utils\Doctrine\Pagination\DoctrinePaginator;
use App\Utils\Domain\Paginator\PaginatorInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrineOrmPaginator;

/**
 * @extends ServiceEntityRepository<Manual>
 */
class ManualRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Manual::class);
    }

    //    /**
    //     * @return Manual[] Returns an array of Manual objects
    //     */
    public function searchManualsPaginated(
        ?string $searchTerm = null,
        string $sortBy = 'timestamp',
        string $sortOrder = 'DESC',
        int $page = 1,
        int $itemsPerPage = 10
    ): PaginatorInterface {
        $queryBuilder = $this->createQueryBuilder('m');

        if ($searchTerm) {
            $queryBuilder
                ->where('m.title LIKE :searchTerm OR m.url LIKE :searchTerm OR m.keyword LIKE :searchTerm')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        $query = $queryBuilder
            ->orderBy('m.' . $sortBy, $sortOrder)
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage)
            ->getQuery();

        return new DoctrinePaginator(new DoctrineOrmPaginator($query));
    }
}
