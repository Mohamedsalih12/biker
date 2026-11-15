<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class BulkDeleteTest extends TestCase
{
    public function test_component_uses_page_specific_table_event_and_model_configuration(): void
    {
        $this->actingAsUser();

        $this->blade(
            '<x-bulk-delete table-name="warehouse-table" event-name="deleteWarehouses" :model="\App\Models\Vehicle::class" />',
        )
            ->assertSee('id="warehouse-table-bulk-delete"', false)
            ->assertSee("tableName: 'warehouse-table'", false)
            ->assertSee("eventName: 'deleteWarehouses'", false)
            ->assertSee("confirmation: 'Delete the selected items?'", false);
    }
}
