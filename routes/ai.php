<?php

use App\Mcp\Servers\EvolveServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('evolve', EvolveServer::class);
