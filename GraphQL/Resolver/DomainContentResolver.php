<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace BD\EzPlatformGraphQLBundle\GraphQL\Resolver;

use BD\EzPlatformGraphQLBundle\GraphQL\InputMapper\SearchQueryMapper;
use BD\EzPlatformGraphQLBundle\GraphQL\Value\ContentFieldValue;
use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Search\SearchHit;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use Overblog\GraphQLBundle\Definition\Argument;
use Overblog\GraphQLBundle\Resolver\TypeResolver;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class DomainContentResolver
{
    /**
     * @var \eZ\Publish\API\Repository\ContentService
     */
    private $contentService;

    /**
     * @var \eZ\Publish\API\Repository\SearchService
     */
    private $searchService;

    /**
     * @var \eZ\Publish\API\Repository\ContentTypeService
     */
    private $contentTypeService;

    /**
     * @var \Overblog\GraphQLBundle\Resolver\TypeResolver
     */
    private $typeResolver;

    /**
     * @var SearchQueryMapper
     */
    private $queryMapper;

    public function __construct(ContentService $contentService, SearchService $searchService, ContentTypeService $contentTypeService, TypeResolver $typeResolver, SearchQueryMapper $queryMapper)
    {
        $this->contentService = $contentService;
        $this->searchService = $searchService;
        $this->contentTypeService = $contentTypeService;
        $this->typeResolver = $typeResolver;
        $this->queryMapper = $queryMapper;
    }

    public function resolveDomainArticles()
    {
        return $this->resolveDomainContentItems('article');
    }

    public function resolveDomainBlogPosts()
    {
        return $this->resolveDomainContentItems('blog_post');
    }

    public function resolveDomainContentItems($contentTypeIdentifier, $query = null)
    {
        return array_map(
            function (Content $content) {
                return $content->contentInfo;
            },
            $this->findContentItemsByTypeIdentifier($contentTypeIdentifier, $query)
        );
    }

    /**
     * @param string $contentTypeIdentifier
     *
     * @param \Overblog\GraphQLBundle\Definition\Argument $args
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content[]
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    private function findContentItemsByTypeIdentifier($contentTypeIdentifier, Argument $args): array
    {
        $queryArg = $args['query'];
        $queryArg['ContentTypeIdentifier'] = $contentTypeIdentifier;
        $args['query'] = $queryArg;

        $query = $this->queryMapper->mapInputToQuery($args['query']);
        $searchResults = $this->searchService->findContent($query);

        return array_map(
            function (SearchHit $searchHit) {
                return $searchHit->valueObject;
            },
            $searchResults->searchHits
        );
    }

    public function resolveDomainSearch()
    {
        $searchResults = $this->searchService->findContentInfo(new Query([]));

        return array_map(
            function (SearchHit $searchHit) {
                return $searchHit->valueObject;
            },
            $searchResults->searchHits
        );
    }

    public function resolveDomainFieldValue($contentInfo, $fieldDefinitionIdentifier)
    {
        $content = $this->contentService->loadContent($contentInfo->id);

        return new ContentFieldValue([
            'contentTypeId' => $contentInfo->contentTypeId,
            'fieldDefIdentifier' => $fieldDefinitionIdentifier,
            'content' => $content,
            'value' => $content->getFieldValue($fieldDefinitionIdentifier)
        ]);
    }

    public function ResolveDomainContentType(ContentInfo $contentInfo)
    {
        static $contentTypesMap = [], $contentTypesLoadErrors = [];

        if (!isset($contentTypesMap[$contentInfo->contentTypeId])) {
            try {
                $contentTypesMap[$contentInfo->contentTypeId] = $this->contentTypeService->loadContentType($contentInfo->contentTypeId);
            } catch (\Exception $e) {
                $contentTypesLoadErrors[$contentInfo->contentTypeId] = $e;
                throw $e;
            }
        }

        return $this->makeDomainContentTypeName($contentTypesMap[$contentInfo->contentTypeId]);
    }

    private function makeDomainContentTypeName(ContentType $contentType)
    {
        $converter = new CamelCaseToSnakeCaseNameConverter(null, false);

        return $converter->denormalize($contentType->identifier) . 'Content';
    }

    public function resolveDomainContentChildren(ContentInfo $contentInfo, $args = [])
    {
        $query = new Query([
            'filter' => new Query\Criterion\LogicalAnd([
                new Query\Criterion\ParentLocationId($contentInfo->mainLocationId)
            ])
        ]);

        return array_map(
            function (SearchHit $searchHit) {
                return $searchHit->valueObject;
            },
            $this->searchService->findContentInfo($query)->searchHits
        );
    }
}
