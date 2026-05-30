<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateContentModel;
use App\Mcp\Tools\DeleteArtifact;
use App\Mcp\Tools\DeleteContentRow;
use App\Mcp\Tools\ListArtifacts;
use App\Mcp\Tools\ListContentModels;
use App\Mcp\Tools\ListContentRows;
use App\Mcp\Tools\ReadArtifact;
use App\Mcp\Tools\ReorderStyles;
use App\Mcp\Tools\UpsertArtifact;
use App\Mcp\Tools\UpsertContentRow;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Evolve')]
#[Version('0.1.0')]
#[Instructions('Use these tools to inspect and safely update Evolve workbench artifacts and content. Mutating tools default to dry_run and destructive tools require confirm_id.')]
class EvolveServer extends Server
{
    protected array $tools = [
        ListArtifacts::class,
        ReadArtifact::class,
        UpsertArtifact::class,
        DeleteArtifact::class,
        ReorderStyles::class,
        ListContentModels::class,
        ListContentRows::class,
        UpsertContentRow::class,
        DeleteContentRow::class,
        CreateContentModel::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
