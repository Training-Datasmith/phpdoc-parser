<?php

declare(strict_types=1);

namespace PHPStan\PhpDocParser\Parser\Doctrine;

/**
 * ApiResource annotation.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @Annotation
 * @Target({"CLASS"})
 */
final class ApiResource
{
    public string $shortName;

    public string $description;

    public string $iri;

    public array $itemOperations;

    public array $collectionOperations;

    public array $attributes = [];

}
