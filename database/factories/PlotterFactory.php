<?php

namespace Database\Factories;

use App\Models\Plotter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plotter>
 */
class PlotterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The description is a closed square, so anything built from it can be
     * asserted against a known shape rather than against its own output.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title_number' => fake()->unique()->bothify('TCT-####-2026'),
            'lot_number' => fake()->bothify('LOT-##'),
            'survey_number' => fake()->bothify('PSD-#####'),
            'area' => fake()->optional()->randomElement(['1,200 sq. m.', '5,000 sq. m.', '8.4 has.']),
            'location' => fake()->optional()->city(),
            'raw_text' => implode("\n", [
                'LINE: 1, BEARING: N. 90 degree, E, DISTANCE: 100 M.',
                'LINE: 2, BEARING: S. 0 degree, E, DISTANCE: 100 M.',
                'LINE: 3, BEARING: N. 90 degree, W, DISTANCE: 100 M.',
                'LINE: 4, BEARING: N. 0 degree, W, DISTANCE: 100 M.',
            ]),
        ];
    }
}
