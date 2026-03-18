<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Multi-Agent Pipeline
    |--------------------------------------------------------------------------
    |
    | When enabled, failing goals will use the Planner → Specialist pipeline
    | instead of the legacy single-agent TaskWorker. Set to true to activate
    | the new multi-agent orchestration framework.
    |
    */

    'use_multi_agent_pipeline' => (bool) env('CAMERON_MULTI_AGENT_PIPELINE', false),

];
