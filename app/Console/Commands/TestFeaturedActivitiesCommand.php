<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\FeaturedActivitiesController;
use Illuminate\Http\Request;

class TestFeaturedActivitiesCommand extends Command
{
    protected $signature = 'test:featured-activities {date?} {location?} {budget?}';
    protected $description = 'Test Featured Activities API';

    public function handle()
    {
        $date = $this->argument('date') ?: '2025-08-09';
        $location = $this->argument('location') ?: 'Hà Nội';
        $budget = $this->argument('budget') ?: 1000000;

        $this->info("Testing Featured Activities API...");
        $this->info("Date: {$date}");
        $this->info("Location: {$location}");
        $this->info("Budget: {$budget}");

        try {
            // Tạo request
            $request = new Request();
            $request->merge([
                'date' => $date,
                'location' => $location,
                'budget' => $budget
            ]);

            // Tạo controller
            $controller = new FeaturedActivitiesController();
            
            // Test API
            $response = $controller->getFeaturedActivities($request);
            $data = $response->getData();

            $this->info("✅ API Test Success!");
            $this->info("Response: " . json_encode($data, JSON_PRETTY_PRINT));

            if (isset($data->date)) {
                $this->info("Date: " . $data->date);
            }
            if (isset($data->total_activities)) {
                $this->info("Total Activities: " . $data->total_activities);
            }
            if (isset($data->user_events)) {
                $this->info("User Events: " . count($data->user_events));
            }
            if (isset($data->smart_suggestions)) {
                $this->info("Smart Suggestions: " . count($data->smart_suggestions));
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->error("File: " . $e->getFile() . ":" . $e->getLine());
        }
    }
}
