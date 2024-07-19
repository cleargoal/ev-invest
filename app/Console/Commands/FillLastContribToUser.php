<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FillLastContribToUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fill-last-contrib-to-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description: command fills lastContribution values to users. For one time only, after field creation.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Retrieve users with their last contribution
        $users = User::with('lastContribution')->get();

        // Iterate over each user
        foreach ($users as $user) {
            // Check if the user has a last contribution
            if ($user->lastContribution !== null) {
                // Update the 'actual_contribution' field
                $user->actual_contribution = $user->lastContribution->amount;
                // Save the changes
                $user->save();
            }
        }

        // Provide feedback in the console
        $this->info('User actual contributions have been updated.');
    }
}
