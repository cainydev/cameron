<?php

declare(strict_types=1);

namespace App\Ai\Attributes;

use App\Enums\ToolCategory;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Category
{
    public function __construct(public ToolCategory $category) {}
}
