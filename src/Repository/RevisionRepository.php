<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\Revision;
use Ramsey\Uuid\UuidInterface;

class RevisionRepository extends EntityRepository
{
    public function findRevision(string $ouuid, string $contentTypeName, ?\DateTimeInterface $dateTime = null): ?Revision
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->join('r.contentType', 'c')
            ->andWhere($qb->expr()->eq('c.name', ':content_type_name'))
            ->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'))
            ->setParameters(['ouuid' => $ouuid, 'content_type_name' => $contentTypeName])
            ->orderBy('r.startTime', 'DESC')
            ->setMaxResults(1);

        if (null === $dateTime) {
            $qb->andWhere($qb->expr()->isNull('r.endTime'));
        } else {
            $format = $this->getEntityManager()->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
            $qb
                ->andWhere($qb->expr()->lte('r.startTime', ':dateTime'))
                ->andWhere($qb->expr()->gte('r.endTime', ':dateTime'))
                ->setParameter('dateTime', $dateTime->format($format));
        }

        $result = $qb->getQuery()->getResult();

        return isset($result[0]) && $result[0] instanceof Revision ? $result[0] : null;
    }

    public function findLatestByOuuid(string $ouuid): ?Revision
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'))
            ->setMaxResults(1)
            ->setParameters(['ouuid' => $ouuid])
            ->orderBy('r.startTime', 'DESC');

        try {
            $result = $qb->getQuery()->getSingleResult();
            if (!$result instanceof Revision) {
                throw new \RuntimeException('Unexpected revision object');
            }

            return $result;
        } catch (NoResultException $e) {
            return null;
        }
    }

    public function findByContentType(ContentType $contentType, $orderBy = null, $limit = null, $offset = null)
    {
        return $this->findBy([
                'contentType' => $contentType,
            ], $orderBy, $limit, $offset);
    }

    /**
     * @param array<mixed> $search
     */
    public function search(array $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->join('r.contentType', 'c')
            ->join('c.environment', 'e');

        if (isset($search['ouuid'])) {
            $qb->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'))->setParameter('ouuid', $search['ouuid']);
        }
        if (isset($search['contentType'])) {
            $qb->andWhere($qb->expr()->eq('r.contentType', ':contentType'))->setParameter('contentType', $search['contentType']);
        }
        if (isset($search['contentTypeName'])) {
            $qb->andWhere($qb->expr()->eq('c.name', ':contentTypeName'))->setParameter('contentTypeName', $search['contentTypeName']);
        }
        if (isset($search['modifiedBefore'])) {
            $qb->andWhere($qb->expr()->lt('r.modified', ':modified'))->setParameter('modified', $search['modifiedBefore']);
        }
        if (isset($search['lockBy'])) {
            $qb->andWhere($qb->expr()->eq('r.lockBy', ':lock_by'))->setParameter('lock_by', $search['lockBy']);
        }
        if (isset($search['archived'])) {
            $qb->andWhere($qb->expr()->eq('r.archived', ':archived'))->setParameter('archived', $search['archived']);
        }
        if (\array_key_exists('endTime', $search)) {
            if (null === $search['endTime']) {
                $qb->andWhere($qb->expr()->isNull('r.endTime'));
            } else {
                $qb->andWhere($qb->expr()->lt('r.endTime', ':endTime'))->setParameter('endTime', $search['endTime']);
            }
        }

        return $qb;
    }

    /**
     * @param string[] $ouuids
     */
    public function searchByEnvironmentOuuids(Environment $environment, array $ouuids): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->join('r.contentType', 'c')
            ->join('c.environment', 'ce')
            ->join('r.environments', 're')
            ->andWhere($qb->expr()->in('r.ouuid', ':ouuids'))
            ->andWhere($qb->expr()->in('re.id', ':environment_id'))
            ->setParameters([
                'environment_id' => $environment->getId(),
                'ouuids' => $ouuids,
            ]);

        return $qb;
    }

    public function save(Revision $revision): void
    {
        $this->_em->persist($revision);
        $this->_em->flush();
    }

    public function addEnvironment(Revision $revision, Environment $environment): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare('insert into environment_revision (environment_id, revision_id) VALUES(:envId, :revId)');
        $stmt->bindValue('envId', $environment->getId());
        $stmt->bindValue('revId', $revision->getId());

        return $stmt->executeStatement();
    }

    public function removeEnvironment(Revision $revision, Environment $environment): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare('delete from environment_revision where environment_id = :envId and revision_id = :revId');
        $stmt->bindValue('envId', $environment->getId());
        $stmt->bindValue('revId', $revision->getId());

        return $stmt->executeStatement();
    }

    /**
     * @param int $page
     *
     * @return Paginator
     */
    public function getRevisionsPaginatorPerEnvironment(Environment $env, $page = 0)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.environments', 'e')
        ->where('e.id = :eid')
        //->andWhere($qb->expr()->eq('r.deleted', ':false')
        ->setMaxResults(50)
        ->setFirstResult($page * 50)
        ->orderBy('r.id', 'asc')
        ->setParameters(['eid' => $env->getId()]);

        $paginator = new Paginator($qb->getQuery());

        return $paginator;
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findOneById(int $id): Revision
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.id = :id')
            ->setParameter('id', $id);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * @param string $hash
     *
     * @return int
     *
     * @throws DBALException
     */
    public function hashReferenced($hash)
    {
        if ('postgresql' === $this->getEntityManager()->getConnection()->getDatabasePlatform()->getName()) {
            $result = $this->getEntityManager()->getConnection()->fetchAll("select count(*) as counter FROM public.revision where raw_data::text like '%$hash%'");

            return \intval($result[0]['counter']);
        }

        try {
            $qb = $this->createQueryBuilder('r')
                ->select('count(r)')
                ->where('r.rawData like :hash')
                ->setParameter('hash', "%$hash%");
            $query = $qb->getQuery();

            return \intval($query->getSingleScalarResult());
        } catch (NonUniqueResultException $e) {
            throw new \RuntimeException(\sprintf('Revision with hash "%s" has non unique results!', $hash));
        }
    }

    /**
     * @param int $page
     *
     * @return Paginator
     */
    public function getRevisionsPaginatorPerEnvironmentAndContentType(Environment $env, ContentType $contentType, $page = 0)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.environments', 'e')
        ->where('e.id = :eid')
        ->andWhere('r.contentType = :ct')
        ->setMaxResults(50)
        ->setFirstResult($page * 50)
        ->orderBy('r.id', 'asc')
        ->setParameters(['eid' => $env->getId(), 'ct' => $contentType]);

        $paginator = new Paginator($qb->getQuery());

        return $paginator;
    }

    /**
     * @param string $ouuid
     *
     * @return Revision
     *
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function findByEnvironment($ouuid, ContentType $contentType, Environment $environment)
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.environments', 'e')
            ->andWhere('r.ouuid = :ouuid')
            ->andWhere('r.contentType = :contentType')
            ->andWhere('e.id = :eid')
            ->setParameter('ouuid', $ouuid)
            ->setParameter('contentType', $contentType)
            ->setParameter('eid', $environment->getId());

        return $qb->getQuery()->getSingleResult();
    }

    public function draftCounterGroupedByContentType($circles, $isAdmin)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.contentType', 'c');
        $qb->select('c.id content_type_id', 'count(c.id) counter');
        $qb->groupBy('c.id');

        $draftConditions = $qb->expr()->andX();
        $draftConditions->add($qb->expr()->eq('r.draft', ':true'));
        $draftConditions->add($qb->expr()->isNull('r.endTime'));

        $draftOrAutoSave = $qb->expr()->orX();
        $draftOrAutoSave->add($draftConditions);
        $draftOrAutoSave->add($qb->expr()->isNotNull('r.autoSave'));

        $and = $qb->expr()->andX();
        $and->add($qb->expr()->eq('r.deleted', ':false'));
        $and->add($draftOrAutoSave);
        $parameters = [
                ':false' => false,
                ':true' => true,
        ];
        if (!$isAdmin) {
            $inCircles = $qb->expr()->orX();
            $inCircles->add($qb->expr()->isNull('r.circles'));
            foreach ($circles as $counter => $circle) {
                $inCircles->add($qb->expr()->like('r.circles', ':circle'.$counter));
                $parameters['circle'.$counter] = '%'.$circle.'%';
            }
            $and->add($inCircles);
        }
        $qb->where($and);

        $qb->setParameters($parameters);

        return $qb->getQuery()->getResult();
    }

    public function findInProgresByContentType($contentType, $circles, $isAdmin)
    {
        $parameters = [
                'contentType' => $contentType,
                'false' => false,
                'true' => true,
        ];

        $qb = $this->createQueryBuilder('r');

        $draftConditions = $qb->expr()->andX();
        $draftConditions->add($qb->expr()->eq('r.draft', ':true'));
        $draftConditions->add($qb->expr()->isNull('r.endTime'));

        $draftOrAutoSave = $qb->expr()->orX();
        $draftOrAutoSave->add($draftConditions);
        $draftOrAutoSave->add($qb->expr()->isNotNull('r.autoSave'));

        $and = $qb->expr()->andX();
        $and->add($qb->expr()->eq('r.deleted', ':false'));
        $and->add($draftOrAutoSave);

        if (!$isAdmin) {
            $inCircles = $qb->expr()->orX();
            $inCircles->add($qb->expr()->isNull('r.circles'));
            foreach ($circles as $counter => $circle) {
                $inCircles->add($qb->expr()->like('r.circles', ':circle'.$counter));
                $parameters['circle'.$counter] = '%'.$circle.'%';
            }
            $and->add($inCircles);
        }

        $qb->where($and)
            ->andWhere($qb->expr()->eq('r.contentType', ':contentType'));

        $qb->setParameters($parameters);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return iterable|Revision[]
     */
    public function findAllDraftsByContentTypeName(string $contentTypeName): iterable
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->join('r.contentType', 'c')
            ->andWhere($qb->expr()->isNull('r.endTime'))
            ->andWhere($qb->expr()->eq('r.draft', $qb->expr()->literal(true)))
            ->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal(false)))
            ->andWhere($qb->expr()->eq('c.name', ':content_type_name'))
            ->setParameter('content_type_name', $contentTypeName);

        foreach ($qb->getQuery()->iterate() as $row) {
            yield $row[0];
        }
    }

    /**
     * @param int   $source
     * @param int   $target
     * @param array $contentTypes
     *
     * @return mixed
     *
     * @throws NonUniqueResultException
     */
    public function countDifferencesBetweenEnvironment($source, $target, $contentTypes = [])
    {
        $sqb = $this->getCompareQueryBuilder($source, $target, $contentTypes);
        $sqb->select('max(r.id)');
        $qb = $this->createQueryBuilder('rev');
        $qb->select('count(rev)');
        $qb->where($qb->expr()->in('rev.id', $sqb->getDQL()));
        $qb->setParameters([
                'false' => false,
                'source' => $source,
                'target' => $target,
        ]);

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param int      $source
     * @param int      $target
     * @param array    $contentTypes
     * @param string[] $ouuids
     *
     * @return QueryBuilder
     */
    private function getCompareQueryBuilder($source, $target, $contentTypes, array $ouuids = [], string $searchValue = '')
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('c.id', 'c.color', 'c.labelField ct_labelField', 'c.name content_type_name', 'c.singularName content_type_singular_name', 'c.icon', 'r.ouuid', "CONCAT(c.name, ':', r.ouuid) AS emsLink", 'max(r.labelField) as item_labelField', 'count(c.id) counter', 'min(concat(e.id, \'/\',r.id, \'/\', r.created, \'/\', r.finalizedBy)) minrevid', 'max(concat(e.id, \'/\',r.id, \'/\', r.created, \'/\', r.finalizedBy)) maxrevid', 'max(r.id) lastRevId')
        ->join('r.contentType', 'c')
        ->join('r.environments', 'e')
        ->where('e.id in (:source, :target)')
        ->andWhere($qb->expr()->eq('r.deleted', ':false'))
        ->andWhere($qb->expr()->eq('c.deleted', ':false'))
        ->groupBy('c.id', 'c.name', 'c.icon', 'r.ouuid', 'c.orderKey')
        ->orHaving('count(r.id) = 1')
        ->orHaving('max(r.id) <> min(r.id)')
        ->setParameters([
            'source' => $source,
            'target' => $target,
            'false' => false,
        ]);

        if (\count($ouuids) > 0) {
            $qb->andWhere($qb->expr()->notin('r.ouuid', $ouuids));
        }

        if (\strlen($searchValue) > 0) {
            $literal = $qb->expr()->literal('%'.\strtolower($searchValue).'%');
            $or = $qb->expr()->orX(
                $qb->expr()->like('LOWER(r.lockBy)', $literal),
                $qb->expr()->like('LOWER(r.autoSaveBy)', $literal),
                $qb->expr()->like('LOWER(r.labelField)', $literal),
            );
            $qb->andWhere($or);
        }

        if (!empty($contentTypes)) {
            $qb->andWhere('c.name in (\''.\implode("','", $contentTypes).'\')');
        }

        return $qb;
    }

    /**
     * @param int    $source
     * @param int    $target
     * @param array  $contentTypes
     * @param int    $from
     * @param int    $limit
     * @param string $orderField
     * @param string $orderDirection
     *
     * @return mixed
     */
    public function compareEnvironment($source, $target, $contentTypes, $from, $limit, $orderField = 'contenttype', $orderDirection = 'ASC')
    {
        switch ($orderField) {
            case 'label':
                $orderField = 'item_labelField';
                break;
            default:
                $orderField = 'c.name';
                break;
        }
        $qb = $this->getCompareQueryBuilder($source, $target, $contentTypes);
        $qb->addOrderBy($orderField, $orderDirection)
        ->addOrderBy('r.ouuid')
        ->setFirstResult($from)
        ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return mixed
     *
     * @throws NonUniqueResultException
     */
    public function countByContentType(ContentType $contentType)
    {
        return $this->createQueryBuilder('a')
        ->select('COUNT(a)')
        ->where('a.contentType = :contentType')
        ->setParameter('contentType', $contentType)
        ->getQuery()
        ->getSingleScalarResult();
    }

    /**
     * @param string $ouuid
     *
     * @return mixed
     *
     * @throws NonUniqueResultException
     */
    public function countRevisions($ouuid, ContentType $contentType)
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r)');
        $qb->where($qb->expr()->eq('r.ouuid', ':ouuid'));
        $qb->andWhere($qb->expr()->eq('r.contentType', ':contentType'));
        $qb->setParameter('ouuid', $ouuid);
        $qb->setParameter('contentType', $contentType);

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string $ouuid
     *
     * @return float|int
     *
     * @throws NonUniqueResultException
     */
    public function revisionsLastPage($ouuid, ContentType $contentType)
    {
        return \floor($this->countRevisions($ouuid, $contentType) / 5.0) + 1;
    }

    /**
     * @param int $page
     *
     * @return float|int
     */
    public function firstElemOfPage($page)
    {
        return ($page - 1) * 5;
    }

    /**
     * @param string $ouuid
     * @param int    $page
     *
     * @return mixed
     */
    public function getAllRevisionsSummary($ouuid, ContentType $contentType, $page = 1)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->select('r', 'e');
        $qb->leftJoin('r.environments', 'e');
        $qb->where($qb->expr()->eq('r.ouuid', ':ouuid'));
        $qb->andWhere($qb->expr()->eq('r.contentType', ':contentType'));
        $qb->setMaxResults(5);
        $qb->setFirstResult(($page - 1) * 5);
        $qb->orderBy('r.created', 'DESC');
        $qb->setParameter('ouuid', $ouuid);
        $qb->setParameter('contentType', $contentType);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Revision|null
     *
     * @throws NonUniqueResultException
     */
    public function findByOuuidContentTypeAndEnvironment(Revision $revision, Environment $env = null)
    {
        if (!$env) {
            $env = $revision->giveContentType()->getEnvironment();
        }

        return $this->findByOuuidAndContentTypeAndEnvironment($revision->giveContentType(), $revision->giveOuuid(), $env);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findByOuuidAndContentTypeAndEnvironment(ContentType $contentType, $ouuid, Environment $env): ?Revision
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->join('r.environments', 'e')
            ->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'))
            ->andWhere($qb->expr()->eq('e.id', ':envId'))
            ->andWhere($qb->expr()->eq('r.contentType', ':contentTypeId'))
            ->setParameters([
                'ouuid' => $ouuid,
                'envId' => $env->getId(),
                'contentTypeId' => $contentType->getId(),
            ]);

        $result = $qb->getQuery()->getResult();

        if (\count($result) > 1) {
            throw new NonUniqueResultException($ouuid.' is publish multiple times in '.$env->getName());
        }

        if (isset($result[0]) && $result[0] instanceof Revision) {
            return $result[0];
        }

        return null;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findIdByOuuidAndContentTypeAndEnvironment(string $ouuid, int $contentType, int $env): ?array
    {
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.environments', 'e');
        $qb->where('r.ouuid = :ouuid and e.id = :envId and r.contentType = :contentTypeId');
        $qb->setParameters([
            'ouuid' => $ouuid,
            'envId' => $env,
            'contentTypeId' => $contentType,
        ]);

        $out = $qb->getQuery()->getArrayResult();
        if (\count($out) > 1) {
            throw new NonUniqueResultException($ouuid.' is publish multiple times in '.$env);
        }

        return $out[0] ?? null;
    }

    /**
     * @param int $revisionId
     *
     * @return mixed
     */
    public function unlockRevision($revisionId)
    {
        $qb = $this->createQueryBuilder('r')->update()
            ->set('r.lockBy', '?1')
            ->set('r.lockUntil', '?2')
            ->where('r.id = ?3')
            ->setParameter(1, null)
            ->setParameter(2, null)
            ->setParameter(3, $revisionId);

        return $qb->getQuery()->execute();
    }

    /**
     * @param int    $revisionId
     * @param string $username
     *
     * @return mixed
     */
    public function lockRevision($revisionId, $username, \DateTimeInterface $lockUntil)
    {
        $qb = $this->createQueryBuilder('r')->update()
            ->set('r.lockBy', '?1')
            ->set('r.lockUntil', '?2')
            ->where('r.id = ?3')
            ->setParameter(1, $username)
            ->setParameter(2, $lockUntil, Type::DATETIME)
            ->setParameter(3, $revisionId);

        return $qb->getQuery()->execute();
    }

    /**
     * @param string $ouuid
     *
     * @return mixed
     */
    public function finaliseRevision(ContentType $contentType, $ouuid, \DateTime $now, string $lockUser)
    {
        $qb = $this->createQueryBuilder('r')->update()
            ->set('r.endTime', '?1')
            ->where('r.contentType = ?2')
            ->andWhere('r.ouuid = ?3')
            ->andWhere('r.endTime is null')
            ->andWhere('r.lockBy  <> ?4 OR r.lockBy is null')
            ->setParameter(1, $now, Type::DATETIME)
            ->setParameter(2, $contentType)
            ->setParameter(3, $ouuid)
            ->setParameter(4, $lockUser);

        return $qb->getQuery()->execute();
    }

    /**
     * @param string $ouuid
     *
     * @return Revision|null
     */
    public function getCurrentRevision(ContentType $contentType, $ouuid)
    {
        $qb = $this->createQueryBuilder('r')->select()
            ->where('r.contentType = ?2')
            ->andWhere('r.ouuid = ?3')
            ->andWhere('r.endTime is null')
            ->setParameter(2, $contentType)
            ->setParameter(3, $ouuid);

        /** @var Revision[] $currentRevision */
        $currentRevision = $qb->getQuery()->execute();
        if (isset($currentRevision[0])) {
            return $currentRevision[0];
        } else {
            return null;
        }
    }

    public function publishRevision(Revision $revision, bool $draft = false)
    {
        $qb = $this->createQueryBuilder('r')->update()
        ->set('r.draft', ':draft')
        ->set('r.lockBy', 'null')
        ->set('r.lockUntil', 'null')
        ->set('r.endTime', 'null')
        ->where('r.id = :id')
        ->setParameters([
                'draft' => $draft,
                'id' => $revision->getId(),
            ]);

        return $qb->getQuery()->execute();
    }

    /**
     * @return mixed
     */
    public function deleteRevision(Revision $revision)
    {
        $qb = $this->createQueryBuilder('r')->update()
        ->set('r.delete', true)
        ->where('r.id = ?1')
        ->setParameter(1, $revision->getId());

        return $qb->getQuery()->execute();
    }

    /**
     * @return mixed
     */
    public function deleteRevisions(ContentType $contentType = null)
    {
        if (null == $contentType) {
            $qb = $this->createQueryBuilder('r');
            $qb->update()
                ->set('r.delete', ':true')
                ->setParameters([
                        'true' => true,
                ]);

            return $qb->getQuery()->execute();
        } else {
            $qb = $this->createQueryBuilder('r')->update();
            $qb->set('r.delete', ':true')
                ->where('r.contentTypeId = :contentTypeId')
                ->setParameters([
                    'true' => true,
                    'contentTypeId' => $contentType->getId(),
                ]);

            return $qb->getQuery()->execute();
        }
    }

    public function lockRevisions(?ContentType $contentType, \DateTime $until, $by, $force = false, ?string $ouuid = null): int
    {
        $qbSelect = $this->createQueryBuilder('s');
        $qbSelect
            ->select('s.id')
            ->andWhere($qbSelect->expr()->isNull('s.endTime'))
            ->andWhere($qbSelect->expr()->eq('s.deleted', $qbSelect->expr()->literal(false)))
            ->andWhere($qbSelect->expr()->eq('s.draft', $qbSelect->expr()->literal(false)))
        ;

        $qbUpdate = $this->createQueryBuilder('r');
        $qbUpdate
            ->update()
            ->set('r.lockBy', ':by')
            ->set('r.lockUntil', ':until')
            ->setParameters(['by' => $by, 'until' => $until]);

        if (null !== $contentType) {
            $qbSelect->andWhere($qbSelect->expr()->eq('s.contentType', ':content_type'));
            $qbUpdate->setParameter('content_type', $contentType);
        }

        if (!$force) {
            $qbSelect->andWhere($qbSelect->expr()->orX(
                $qbSelect->expr()->lt('s.lockUntil', ':now'),
                $qbSelect->expr()->isNull('s.lockUntil')
            ));
            $qbUpdate->setParameter('now', new \DateTime());
        }

        if (null !== $ouuid) {
            $qbSelect->andWhere($qbSelect->expr()->eq('s.ouuid', ':ouuid'));
            $qbUpdate->setParameter('ouuid', $ouuid);
        }

        $qbUpdate->andWhere($qbUpdate->expr()->in('r.id', $qbSelect->getDQL()));

        return $qbUpdate->getQuery()->execute();
    }

    /**
     * @param int[] $ids
     */
    public function lockRevisionsById(array $ids, string $by, \DateTime $until): int
    {
        $qbUpdate = $this->createQueryBuilder('r');
        $qbUpdate
            ->update()
            ->set('r.lockBy', ':by')
            ->set('r.lockUntil', ':until')
            ->andWhere($qbUpdate->expr()->in('r.id', ':ids'))
            ->setParameters(['ids' => $ids, 'by' => $by, 'until' => $until]);

        return $qbUpdate->getQuery()->execute();
    }

    public function lockAllRevisions(\DateTime $until, string $by): int
    {
        return $this->lockRevisions(null, $until, $by, true);
    }

    public function unlockRevisions(?ContentType $contentType, string $by): int
    {
        $qbSelect = $this->createQueryBuilder('s');
        $qbSelect
            ->select('s.id')
            ->andWhere($qbSelect->expr()->eq('s.lockBy', ':by'))
            ->andWhere($qbSelect->expr()->isNull('s.endTime'))
            ->andWhere($qbSelect->expr()->eq('s.deleted', $qbSelect->expr()->literal(false)))
            ->andWhere($qbSelect->expr()->eq('s.draft', $qbSelect->expr()->literal(false)))
        ;

        $qbUpdate = $this->createQueryBuilder('u');
        $qbUpdate
            ->update()
            ->set('u.lockBy', ':null')
            ->set('u.lockUntil', ':null')
            ->setParameters(['by' => $by, 'null' => null])
        ;

        if (null !== $contentType) {
            $qbSelect->andWhere($qbSelect->expr()->eq('s.contentType', ':content_type'));
            $qbUpdate->setParameter('content_type', $contentType);
        }

        $qbUpdate->andWhere($qbUpdate->expr()->in('u.id', $qbSelect->getDQL()));

        return $qbUpdate->getQuery()->execute();
    }

    /**
     * @param int[] $ids
     */
    public function unlockRevisionsById(array $ids): int
    {
        $qbUpdate = $this->createQueryBuilder('r');
        $qbUpdate
            ->update()
            ->set('r.lockBy', ':null')
            ->set('r.lockUntil', ':null')
            ->andWhere($qbUpdate->expr()->in('r.id', ':ids'))
            ->setParameters(['ids' => $ids, 'null' => null]);

        return $qbUpdate->getQuery()->execute();
    }

    public function unlockAllRevisions(string $by): int
    {
        return $this->unlockRevisions(null, $by);
    }

    public function findAllLockedRevisions(ContentType $contentType, string $lockBy, int $page = 0, int $limit = 50): Paginator
    {
        /** @var QueryBuilder $qb */
        $qb = $this->createQueryBuilder('r');
        $qb
            ->andWhere($qb->expr()->eq('r.contentType', ':content_type'))
            ->andWhere($qb->expr()->eq('r.lockBy', ':username'))
            ->andWhere($qb->expr()->isNull('r.endTime'))
            ->setMaxResults($limit)
            ->setFirstResult($page * $limit)
            ->orderBy('r.id', 'asc')
            ->setParameters([
                'content_type' => $contentType,
                'username' => $lockBy,
            ])
        ;

        return new Paginator($qb->getQuery());
    }

    /**
     * @return Revision[]
     */
    public function findAllPublishedRevision(EMSLink $documentLink): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.contentType', 'c');
        $qb->join('r.environments', 'e');

        $qb->andWhere($qb->expr()->eq('r.ouuid', ':ouuid'));
        $qb->andWhere($qb->expr()->eq('c.name', ':contentType'));
        $qb->andWhere($qb->expr()->eq('c.deleted', ':false'));
        $qb->andWhere($qb->expr()->eq('c.active', ':true'));
        $qb->andWhere($qb->expr()->eq('r.deleted', ':false'));
        $qb->andWhere($qb->expr()->isNotNull('e.id'));

        $qb->setParameters([
            'ouuid' => $documentLink->getOuuid(),
            'contentType' => $documentLink->getContentType(),
            'true' => true,
            'false' => false,
        ]);

        return $qb->getQuery()->execute();
    }

    public function findDraftsByContentType(ContentType $contentType): array
    {
        $qbSelect = $this->createQueryBuilder('s');
        $qbSelect
            ->andWhere($qbSelect->expr()->eq('s.contentType', ':content_type'))
            ->andWhere($qbSelect->expr()->eq('s.draft', $qbSelect->expr()->literal(true)))
            ->andWhere($qbSelect->expr()->eq('s.deleted', $qbSelect->expr()->literal(false)))
            ->andWhere($qbSelect->expr()->isNull('s.endTime'))
            ->orderBy('s.id', 'asc')
            ->setParameters(['content_type' => $contentType])
        ;

        return $qbSelect->getQuery()->execute();
    }

    public function findAllDrafts(): array
    {
        $qbSelect = $this->createQueryBuilder('s');
        $qbSelect
            ->andWhere($qbSelect->expr()->eq('s.draft', $qbSelect->expr()->literal(true)))
            ->andWhere($qbSelect->expr()->eq('s.deleted', $qbSelect->expr()->literal(false)))
            ->andWhere($qbSelect->expr()->isNull('s.endTime'))
            ->orderBy('s.id', 'asc')
        ;

        return $qbSelect->getQuery()->execute();
    }

    public function countDraftInProgress(string $searchValue, ?ContentType $context): int
    {
        $qb = $this->createQueryBuilder('rev');
        $qb->select('count(rev.id)');
        $qb->andWhere($qb->expr()->eq('rev.draft', ':true'));
        $qb->andWhere($qb->expr()->eq('rev.deleted', ':false'));
        $qb->setParameters([
            ':true' => true,
            ':false' => false,
        ]);

        if (null !== $context) {
            $qb->andWhere($qb->expr()->eq('rev.contentType', ':contentType'));
            $qb->setParameter('contentType', $context);
        }
        $this->addSearchValueFilter($qb, $searchValue);

        return \intval($qb->getQuery()->getSingleScalarResult());
    }

    /**
     * @return Revision[]
     */
    public function getDraftInProgress(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, ?ContentType $context): array
    {
        $qb = $this->createQueryBuilder('rev');
        $qb->andWhere($qb->expr()->eq('rev.draft', ':true'));
        $qb->andWhere($qb->expr()->eq('rev.deleted', ':false'));
        $qb->setParameters([
            ':true' => true,
            ':false' => false,
        ]);

        if (null !== $context) {
            $qb->andWhere($qb->expr()->eq('rev.contentType', ':contentType'));
            $qb->setParameter('contentType', $context);
        }
        $qb->setFirstResult($from)
        ->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy(\sprintf('rev.%s', $orderField), $orderDirection);
        }
        $this->addSearchValueFilter($qb, $searchValue);

        return $qb->getQuery()->execute();
    }

    public function findLatestVersion(ContentType $contentType, string $versionOuuid): ?Revision
    {
        $toField = $contentType->getVersionDateToField();

        $qb = $this->createQueryBuilder('r');
        $qb
            ->andWhere($qb->expr()->eq('r.versionUuid', ':version_ouuid'))
            ->andWhere($qb->expr()->isNull('r.endTime'))
            ->setParameter('version_ouuid', $versionOuuid);

        /** @var Revision[] $revisions */
        $revisions = $qb->getQuery()->getResult();

        foreach ($revisions as $revision) {
            $toFieldValue = $revision->getRawData()[$toField] ?? 'latest';
            if ('latest' === $toFieldValue) {
                return $revision;
            }
        }

        return null;
    }

    private function addSearchValueFilter(QueryBuilder $qb, string $searchValue): void
    {
        if (\strlen($searchValue) > 0) {
            $or = $qb->expr()->orX(
                $qb->expr()->like('LOWER(rev.lockBy)', ':term'),
                $qb->expr()->like('LOWER(rev.autoSaveBy)', ':term'),
                $qb->expr()->like('LOWER(rev.labelField)', ':term'),
            );
            $qb->andWhere($or)
                ->setParameter(':term', '%'.\strtolower($searchValue).'%');
        }
    }

    /**
     * @param string[] $contentTypes
     *
     * @return Revision[]
     */
    public function getAvailableRevisionsForRelease(int $from, int $size, Release $release, array $contentTypes, ?string $orderField, string $orderDirection, string $searchValue): array
    {
        $qb = $this->getCompareQueryBuilder($release->getEnvironmentSource()->getId(), $release->getEnvironmentTarget()->getId(), $contentTypes, $release->getRevisionsOuuids(), $searchValue);
        if (null === $orderField) {
            $qb->orderBy('r.ouuid', $orderDirection);
        } else {
            $qb->orderBy('r.ouuid', $orderDirection);
        }
        $qb->setFirstResult($from)
            ->setMaxResults($size);

        return $qb->getQuery()->execute();
    }

    /**
     * @param string[] $contentTypes
     */
    public function countAvailableRevisionsForRelease(Release $release, array $contentTypes, string $searchValue): int
    {
        $sqb = $this->getCompareQueryBuilder($release->getEnvironmentSource()->getId(), $release->getEnvironmentTarget()->getId(), $contentTypes, $release->getRevisionsOuuids(), $searchValue);
        $sqb->select('max(r.id)');

        $qb = $this->createQueryBuilder('rev');
        $qb->select('count(rev)');
        $qb->where($qb->expr()->in('rev.id', $sqb->getDQL()));
        $qb->setParameters([
            'source' => $release->getEnvironmentSource()->getId(),
            'target' => $release->getEnvironmentTarget()->getId(),
            'false' => false,
        ]);
        $query = $qb->getQuery();

        return \intval($query->getSingleScalarResult());
    }

    /**
     * @return Revision[]
     */
    public function findAllByVersionUuid(UuidInterface $versionUuid, Environment $defaultEnvironment): array
    {
        $qb = $this->createQueryBuilder('r');
        $qb
            ->addSelect('e')
            ->join('r.contentType', 'c')
            ->join('r.environments', 'e')
            ->andWhere($qb->expr()->eq('e.id', ':environment_id'))
            ->andWhere($qb->expr()->eq('r.versionUuid', ':version_uuid'))
            ->andWhere($qb->expr()->eq('c.deleted', $qb->expr()->literal(false)))
            ->andWhere($qb->expr()->eq('c.active', $qb->expr()->literal(true)))
            ->andWhere($qb->expr()->eq('r.deleted', $qb->expr()->literal(false)))
            ->setParameters([
                'version_uuid' => $versionUuid,
                'environment_id' => $defaultEnvironment->getId(),
            ]);

        return $qb->getQuery()->execute();
    }
}
