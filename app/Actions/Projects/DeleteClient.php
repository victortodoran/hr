<?php

namespace App\Actions\Projects;

use App\Models\Client;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a Client, refusing while it still has Projects.
 */
class DeleteClient
{
    /**
     * @throws ValidationException
     */
    public function delete(Client $client): void
    {
        if ($client->projects()->exists()) {
            throw ValidationException::withMessages([
                'client' => __(':client still has Projects. Move or delete them before deleting the Client.', [
                    'client' => $client->name,
                ]),
            ]);
        }

        $client->delete();
    }
}
