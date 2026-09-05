<?php

declare(strict_types=1);

namespace Capell\Search\Manifest;

use Capell\Core\Contracts\Agent\DefinesAgentTool;
use Capell\Core\Data\Agent\AgentToolBindingData;
use Capell\Core\Data\Agent\AgentToolDefinitionData;
use Capell\Core\Enums\Agent\AgentToolBindingType;
use Capell\Core\Enums\Agent\AgentToolEffect;
use Override;

final class SearchAgentToolContribution implements DefinesAgentTool
{
    #[Override]
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }

    #[Override]
    public static function agentToolDefinition(): AgentToolDefinitionData
    {
        return new AgentToolDefinitionData(
            name: 'site.search',
            description: __('capell-search::agent.tools.site_search'),
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'q' => ['type' => 'string', 'maxLength' => 200],
                ],
                'required' => ['q'],
                'additionalProperties' => false,
            ],
            outputSchema: ['type' => 'object'],
            effect: AgentToolEffect::Read,
            binding: new AgentToolBindingData(
                type: AgentToolBindingType::Endpoint,
                target: '/agent/v1/search',
            ),
        );
    }
}
