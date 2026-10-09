<?php

use App\Domain\Project\Broadcasting\ProjectChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('projects.{projectId}', ProjectChannel::class);
