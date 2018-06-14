<?php

namespace BD\EzPlatformGraphQLBundle\GraphQL\Relay;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use Overblog\GraphQLBundle\Relay\Node\GlobalId;
use Overblog\GraphQLBundle\Resolver\TypeResolver;

class NodeResolver
{
    /**
     * @var ContentService
     */
    private $contentService;
    /**
     * @var TypeResolver
     */
    private $typeResolver;

    public function __construct(ContentService $contentService, TypeResolver $typeResolver)
    {
        $this->contentService = $contentService;
        $this->typeResolver = $typeResolver;
    }

    /**
     * @param $globalId
     *
     * @return null|\eZ\Publish\API\Repository\Values\Content\ContentInfo
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function resolveNode($globalId)
    {
        $params = GlobalId::fromGlobalId($globalId);

        if (in_array($params['type'], ['Content'])) {
            return $this->contentService->loadContentInfo($params['id']);
        }

        return null;
    }

    /**
     * @param $object
     *
     * @return \GraphQL\Type\Definition\Type
     */
    public function resolveType($object)
    {
        return $this->typeResolver->resolve('Content');
    }
}
