<?php

namespace EMS\CoreBundle\Service;

use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Field\ObjectChoiceListItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ObjectChoiceCacheService
{
    /** @var LoggerInterface */
    private $logger;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;
    /** @var TokenStorageInterface */
    protected $tokenStorage;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var bool[] */
    private $fullyLoaded;
    /** @var array<ObjectChoiceListItem[]> */
    private $cache;

    public function __construct(LoggerInterface $logger, ContentTypeService $contentTypeService, AuthorizationCheckerInterface $authorizationChecker, TokenStorageInterface $tokenStorage, ElasticaService $elasticaService)
    {
        $this->logger = $logger;
        $this->contentTypeService = $contentTypeService;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->elasticaService = $elasticaService;

        $this->fullyLoaded = [];
        $this->cache = [];
    }

    /**
     * @param ObjectChoiceListItem[] $choices
     */
    public function loadAll(array &$choices, string $types, bool $circleOnly = false, bool $withWarning = true): void
    {
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface) {
            throw new \RuntimeException('Unexpected security token object');
        }
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Unexpected user entity object');
        }

        $cts = \explode(',', $types);
        foreach ($cts as $type) {
            if (!($this->fullyLoaded[$type] ?? false)) {
                $currentType = $this->contentTypeService->getByName($type);
                if (false !== $currentType) {
                    $index = $this->contentTypeService->getIndex($currentType);

                    if ($circleOnly && !$this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT')) {
                        $circles = $user->getCircles();
                        $ouuids = [];
                        foreach ($circles as $circle) {
                            \preg_match('/(?P<type>(\w|-)+):(?P<ouuid>(\w|-)+)/', $circle, $matches);
                            $ouuids[] = $matches['ouuid'];
                        }
                        $search = $this->elasticaService->generateTermsSearch([$index], '_id', $ouuids);
                    } else {
                        $search = new Search([$index]);
                    }

                    $search->setContentTypes([$type]);

                    if (\is_string($currentType->getSortBy()) && \strlen($currentType->getSortBy()) > 0) {
                        $search->setSort([
                            $currentType->getSortBy() => [
                                'order' => $currentType->getSortOrder() ?? 'asc',
                                'missing' => '_last',
                            ],
                        ]);
                    } elseif ($currentType->getOrderField()) {
                        $search->setSort([
                            $currentType->getOrderField() => [
                                'order' => 'asc',
                                'missing' => '_last',
                            ],
                        ]);
                    }
                    $search->setSources($currentType->getRenderingSourceFields());
                    $search->setSize(1000);
                    $resultSet = $this->elasticaService->search($search);
                    if ($resultSet->count() > 1000) {
                        $this->logger->warning('service.object_choice_cache.limited_result_set', [
                            'count' => $resultSet->count(),
                            'limit' => 1000,
                        ]);
                    }

                    foreach ($resultSet as $result) {
                        if (false === $result) {
                            continue;
                        }
                        $hitDocument = Document::fromResult($result);
                        if (!isset($choices[$hitDocument->getEmsId()])) {
                            $itemContentType = $this->contentTypeService->getByName($hitDocument->getContentType());
                            $listItem = new ObjectChoiceListItem($hitDocument, $itemContentType ? $itemContentType : null);
                            $choices[$listItem->getValue()] = $listItem;
                            $this->cache[$hitDocument->getContentType()][$hitDocument->getId()] = $listItem;
                        }
                    }
                } elseif ($withWarning) {
                    $this->logger->warning('service.object_choice_cache.contenttype_not_found', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $type,
                    ]);
                }
                $this->fullyLoaded[$type] = true;
            } else {
                foreach ($this->cache[$type] ?? [] as $id => $item) {
                    if (!isset($choices[$type.':'.$id])) {
                        $choices[$type.':'.$id] = $item;
                    }
                }
            }
        }
    }

    /**
     * @param string[] $objectIds
     *
     * @return ObjectChoiceListItem[]
     */
    public function load(array $objectIds, bool $circleOnly = false, bool $withWarning = true): array
    {
        $choices = [];
        $missingOuuidsPerIndexAndType = [];
        foreach ($objectIds as $objectId) {
            if (\is_string($objectId) && false !== \strpos($objectId, ':')) {
                list($objectType, $objectOuuid) = \explode(':', $objectId);
                if (!isset($this->cache[$objectType])) {
                    $this->cache[$objectType] = [];
                }

                if (isset($this->cache[$objectType][$objectOuuid])) {
                    $choices[$objectId] = $this->cache[$objectType][$objectOuuid];
                } else {
                    if (!isset($this->fullyLoaded[$objectType])) {
                        $contentType = $this->contentTypeService->getByName($objectType);
                        if ($contentType) {
                            $index = $this->contentTypeService->getIndex($contentType);
                            if ($index) {
                                $missingOuuidsPerIndexAndType[$index][$objectType][] = $objectOuuid;
                            } elseif ($withWarning) {
                                $this->logger->warning('service.object_choice_cache.alias_not_found', [
                                    EmsFields::LOG_CONTENTTYPE_FIELD => $objectType,
                                ]);
                            }
                        } elseif ($withWarning) {
                            $this->logger->warning('service.object_choice_cache.contenttype_not_found', [
                                EmsFields::LOG_CONTENTTYPE_FIELD => $objectType,
                            ]);
                        }
                    }
                }
            } else {
                if (null !== $objectId && '' !== $objectId && $withWarning) {
                    $this->logger->warning('service.object_choice_cache.object_key_not_found', [
                        'object_key' => $objectId,
                    ]);
                }
            }
        }

        foreach ($missingOuuidsPerIndexAndType as $indexName => $missingOuuidsPerType) {
            $boolQuery = $this->elasticaService->getBoolQuery();
            $sourceField = [];
            foreach ($missingOuuidsPerType as $type => $ouuids) {
                $contentType = $this->contentTypeService->getByName($type);
                if (false === $contentType) {
                    continue;
                }

                if ($contentType->hasVersionTags() && null !== $dateToField = $contentType->getVersionDateToField()) {
                    $contentTypeQuery = new BoolQuery();
                    $contentTypeQuery->addMust($this->elasticaService->getTermsQuery(Mapping::VERSION_UUID, $ouuids));
                    $contentTypeQuery->addMustNot(new Exists($dateToField));
                } else {
                    $contentTypeQuery = $this->elasticaService->getTermsQuery('_id', $ouuids);
                }

                $ouuidsQuery = $this->elasticaService->filterByContentTypes($contentTypeQuery, [$type]);
                if (null !== $ouuidsQuery) {
                    $boolQuery->addShould($ouuidsQuery);
                }

                $sourceField = \array_unique(\array_merge($sourceField, $contentType->getRenderingSourceFields()), SORT_STRING);
            }
            $boolQuery->setMinimumShouldMatch(1);

            $search = new Search([$indexName], $boolQuery);
            $search->setSources($sourceField);

            $scroll = $this->elasticaService->scroll($search);
            foreach ($scroll as $resultSet) {
                foreach ($resultSet as $result) {
                    if (false === $result) {
                        continue;
                    }
                    $document = Document::fromResult($result);
                    $contentType = $this->contentTypeService->getByName($document->getContentType());
                    if (false === $contentType) {
                        continue;
                    }
                    $listItem = new ObjectChoiceListItem($document, $contentType);
                    $this->cache[$document->getContentType()][$document->getId()] = $listItem;
                    $choices[$document->getEmsId()] = $listItem;
                }
            }
        }

        return $choices;
    }
}
