<?php

namespace AppBundle\Repository;

use AppBundle\Entity\ArticleCategory;
use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\Article;

/**
 * Class ArticleRepository
 */
class ArticleRepository extends EntityRepository
{
    /**
     * @param int $nb
     *
     * @return Article[]
     */
    public function getLastArticlesWithCover($nb = 10)
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.medias', 'am')
            ->where('am.media IS NOT NULL')
            ->andWhere('a.published = true')
            ->groupBy('a.id')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($nb);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int $nb
     *
     * @return Article[]
     */
    public function getLastArticles($nb = 3)
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.medias', 'am')
            ->where('a.published = true')
            ->groupBy('a.id')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($nb);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param ArticleCategory $category
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getQbArticlesInCategory(ArticleCategory $category)
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.medias', 'am')
            ->where('a.category = :category')
            ->andWhere('a.published = true')
            ->setParameter('category', $category)
            ->orderBy('a.createdAt', 'DESC');

        return $qb;
    }

    public function getLastArticlesInCategory(ArticleCategory $category, $nb)
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.medias', 'am')
            ->where('a.category = :category')
            ->andWhere('a.published = true')
            ->setParameter('category', $category)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($nb);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getQbAllArticles()
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.medias', 'am')
            ->where('a.published = true')
            ->orderBy('a.createdAt', 'DESC');

        return $qb;
    }
}
