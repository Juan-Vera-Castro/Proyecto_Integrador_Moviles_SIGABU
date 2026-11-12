<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo_unico' => fake()->unique()->numerify('#######'),
            'contrasena_hash' => static::$password ??= 'password',
            'id_rol' => Role::query()->value('id_rol') ?? 1,
            'estado_activo' => true,
        ];
    }
}
