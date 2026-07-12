<?php

namespace Database\Seeders;

use App\Models\Card;
use Illuminate\Database\Seeder;

class CardSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            ['Miss the bus', 'You watch it pull away just as you reach the stop.'],
            ['Lose your keys', 'Every pocket is empty and the spare key is nowhere nearby.'],
            ['Crack your phone screen', 'One short drop leaves a spiderweb across the glass.'],
            ['Get caught in heavy rain', 'No umbrella, no shelter, and a long walk ahead.'],
            ['Forget an important birthday', 'You remember only after seeing everyone else celebrating.'],
            ['Step on a toy', 'A tiny piece of plastic finds the most painful spot on your foot.'],
            ['Receive a parking ticket', 'A bright notice waits under the windshield wiper.'],
            ['Delete an unsaved document', 'Hours of work disappear after one wrong click.'],
            ['Get food poisoning', 'Dinner fights back and keeps you close to the bathroom.'],
            ['Miss a flight', 'The gate closes while your plane is still sitting outside.'],
            ['Lose your wallet', 'Your cash, cards, and ID vanish at the same time.'],
            ['Have a toothache', 'A sharp pulse makes every bite and every minute hurt.'],
            ['Get locked out', 'Your keys are inside and the door is definitely locked.'],
            ['Internet stops working', 'The connection dies exactly when you need it most.'],
            ['Spill coffee on your laptop', 'One tipped cup heads straight for the keyboard.'],
            ['Wake up late', 'The alarm failed and you are already supposed to be there.'],
            ['Get a flat tire', 'The car starts pulling sideways far from a convenient stop.'],
            ['Fail an exam', 'All that studying ends with a score below the passing line.'],
            ['Have your card declined', 'The terminal beeps while a queue waits behind you.'],
            ['Break your favorite mug', 'Your trusted cup becomes a pile of ceramic pieces.'],
        ];

        for ($i = 1; $i <= 100; $i++) {
            [$title, $subtitle] = $events[($i - 1) % count($events)];
            $score = round((($i * 37) % 1000) / 10, 1);
            $card = Card::where('title', $title.' #'.$i)->first()
                ?? Card::where('title', $title)->where('score', $score)->first()
                ?? new Card();
            $card->fill([
                'title' => $title,
                'subtitle' => $subtitle,
                'score' => $score,
                'image' => '0',
                'deck' => $i % 5 === 0 ? 'spicy' : 'normal',
            ])->save();
        }
    }
}
