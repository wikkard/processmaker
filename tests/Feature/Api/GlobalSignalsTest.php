<?php

namespace Tests\Feature\Api;

use ProcessMaker\Models\ProcessRequest;
use Tests\Feature\Shared\ProcessTestingTrait;
use Tests\Feature\Shared\RequestHelper;
use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class GlobalSignalsTest extends TestCase
{
    use RequestHelper;
    use ProcessTestingTrait;

    /**
     * Test a definition with two processes and signals
     *
     * @group process_tests
     */
    public function testGlobalSignals()
    {
        $this->logExecutionEvents();
        // Script task requires passport installed (oauth token)
        Artisan::call('passport:install', ['-vvv' => true]);

        // Create the processes
        $parent = $this->createProcess([
            'id' => 1,
            'bpmn' => file_get_contents(__DIR__ . '/processes/GlobalSignals.bpmn')
        ]);

        // Start a parent process instance
        $instance = $this->startProcess($parent, '_3');

        // Assertion: Active Task = Task 1
        $activeTask = $instance->tokens()->where('status', 'ACTIVE')->first();
        $this->assertEquals('Task 1', $activeTask->element_name);
        // Complete Parent Task
        $this->completeTask($activeTask, []);

        // Assertion: There are 2 Requests started
        $this->assertEquals(2, ProcessRequest::count());

        // Assertion: Active Task = Task 2 (in process two)
        $childRequest = ProcessRequest::orderBy('id', 'desc')->first();
        $activeTask = $childRequest->tokens()->where('status', 'ACTIVE')->first();
        $this->assertEquals('Task 2', $activeTask->element_name);
        // Complete Child Task
        $this->completeTask($activeTask, []);

        // Assertion: Active Task = Task 3 (process one)
        $instance->refresh();
        $activeTask = $instance->tokens()->where('status', 'ACTIVE')->first();
        $this->assertEquals('Task 3', $activeTask->element_name);
        // Complete Parent Task
        $this->completeTask($activeTask, []);

        // Assertion: Active Task = Task 4b (process two)
        $childRequest->refresh();
        $activeTask = $childRequest->tokens()->where('status', 'ACTIVE')->first();
        $this->assertEquals('Task 4b', $activeTask->element_name);
        // Complete Parent Task
        $this->completeTask($activeTask, []);

        // Assertion: Active Task = Task 4a (process one)
        $instance->refresh();
        $activeTask = $instance->tokens()->where('status', 'ACTIVE')->first();
        $this->assertEquals('Task 4a', $activeTask->element_name);
        // Complete Parent Task
        $this->completeTask($activeTask, []);

        // Get active tokens
        $instance->refresh();
        $childRequest->refresh();
        $activeTokensParent = $instance->tokens()->where('status', 'ACTIVE')->get();
        $activeTokensChild = $childRequest->tokens()->where('status', 'ACTIVE')->get();

        // Assertion: All the request were completed
        $this->assertCount(0, $activeTokensParent);
        $this->assertCount(0, $activeTokensChild);
        $this->assertEquals('COMPLETED', $instance->status);
        $this->assertEquals('COMPLETED', $childRequest->status);
    }
}