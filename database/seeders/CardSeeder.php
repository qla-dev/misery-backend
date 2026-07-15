<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Stack;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CardSeeder extends Seeder
{
    protected bool $scoresOnly = false;

    public function run(): void
    {
        $normal = Stack::updateOrCreate(['slug' => 'normal'], ['name' => 'Normal', 'color' => '#facc15', 'icon_key' => 'sparkles', 'description' => 'Funny and awkward situations', 'description_bs' => 'Smiješne i čudne situacije']);
        $spicy = Stack::updateOrCreate(['slug' => 'spicy'], ['name' => 'Spicy', 'color' => '#fb7185', 'icon_key' => 'flame', 'description' => 'Friendly, absurd and wildly unfortunate', 'description_bs' => 'Prijateljski, apsurdno i divlje']);
        $adult = Stack::updateOrCreate(['slug' => '18-plus'], ['name' => '18+', 'color' => '#ef4444', 'icon_key' => 'shield-alert', 'description' => 'Explicit sexual situations for adults only', 'description_bs' => 'Eksplicitne seksualne situacije samo za odrasle']);

        $events = [
            // Travel disasters
            ['Lose Your Passport at the Boarding Gate', 'Your flight is boarding while every pocket and bag comes up empty.', 85.4],
            ['Arrive at the Wrong Airport', 'Your plane leaves from the other side of the city in less than an hour.', 67.8],
            ['Airline Loses the Bag With Your Medicine', 'The carousel stops and everything essential is somewhere else.', 72.6],
            ['Rental Car Gets Towed Abroad', 'You return to an empty street with no idea who took the car.', 71.3],
            ['Run Out of Fuel in the Desert', 'The engine dies where the road, shade, and phone signal all disappear.', 89.1],
            ['Tire Explodes on a Busy Highway', 'The car jerks sideways while traffic is flying past on both sides.', 83.7],
            ['Engine Dies Inside a Long Tunnel', 'The dashboard goes dark as headlights stack up behind you.', 78.9],
            ['Windshield Shatters While Driving', 'A sudden impact turns the road ahead into a web of broken glass.', 81.6],
            ['Miss the Last Ferry Off the Island', 'The boat pulls away with your hotel, luggage, and plans on the mainland.', 69.5],
            ['Hotel Loses Your Reservation During a Festival', 'Every room in town is sold out and reception has never heard of you.', 61.8],

            // Home and property disasters
            ['A Pipe Bursts Above Your Bedroom', 'The ceiling starts raining directly onto your bed and electronics.', 82.4],
            ['Sewage Backs Up During a Dinner Party', 'The smell arrives first, followed by something much worse.', 79.7],
            ['A Storm Tears Open Your Roof', 'Rain pours through the house while pieces of roof vanish into the night.', 91.2],
            ['Basement Floods With Every Family Album', 'The water reaches the shelves holding memories that have no backup.', 86.5],
            ['An Oven Fire Takes Over the Kitchen', 'The flames ignore the extinguisher and start climbing the cabinets.', 88.8],
            ['The Refrigerator Dies While You Are Away', 'You open the door after vacation and immediately regret coming home.', 64.2],
            ['Heating Fails During a Blizzard', 'The indoor temperature keeps dropping while every repair service is closed.', 87.6],
            ['Air Conditioning Fails in a Record Heatwave', 'The apartment becomes an oven and the windows offer no relief.', 70.4],
            ['Ceiling Collapses During Dinner', 'Plaster, pipes, and insulation land where everyone was just sitting.', 90.1],
            ['A Tree Crushes Your Car and Garage', 'One enormous branch turns two expensive problems into one wreck.', 93.3],

            // Work and technology disasters
            ['Delete Your Finished Thesis Without a Backup', 'Months of work disappear one day before the final deadline.', 84.6],
            ['Ransomware Locks Every Company File', 'A countdown demands payment while the entire office looks at you.', 95.8],
            ['Laptop Is Stolen Before the Big Presentation', 'The only copy of the pitch leaves in someone else’s backpack.', 76.1],
            ['Email the Salary Spreadsheet to the Whole Company', 'Everyone can now compare exactly what everyone else earns.', 73.9],
            ['Send a Complaint About Someone Directly to Them', 'Their name was supposed to be in the message, not the recipient box.', 68.7],
            ['Your Microphone Stays Live While Insulting a Client', 'The meeting goes silent just as you realize they heard every word.', 66.3],
            ['Computer Crashes During a Live Keynote', 'The giant screen freezes on the one slide nobody was meant to see.', 60.5],
            ['Erase the Production Database', 'One command removes the live data while thousands of users are online.', 97.4],
            ['Cloud Sync Overwrites Every Family Photo', 'The empty folder becomes the newest version on every device.', 80.8],
            ['Send a Private Screenshot Back to Its Subject', 'The person you mocked receives the screenshot and your commentary.', 74.8],

            // Money and legal disasters
            ['Lose Your Wallet in a Foreign Country', 'Cash, cards, identification, and hotel key disappear together.', 77.2],
            ['Bank Freezes Your Accounts While You Are Abroad', 'Every payment fails and support says to visit a branch in person.', 82.9],
            ['Discover Someone Stole Your Identity', 'Loans and contracts appear under your name just as you apply for a home.', 89.7],
            ['Get Audited After Losing Every Receipt', 'The tax office wants proof from the one year your records vanished.', 75.6],
            ['Accidentally Win an Auction You Cannot Afford', 'Your joke bid becomes a legally binding and extremely expensive purchase.', 58.9],
            ['Payroll Sends Everyone Double From Your Account', 'The staff celebrates while the company balance drops below zero.', 92.5],
            ['Wire Your House Deposit to the Wrong Account', 'The bank confirms the transfer and cannot promise the money will return.', 98.2],
            ['Wash an Envelope Full of Cash', 'The washing machine finishes with a pocket full of expensive confetti.', 55.7],
            ['Your Card Is Declined at an Anniversary Dinner', 'The waiter returns while your date and the entire queue watch.', 57.4],
            ['Total Your Car the Day Insurance Expires', 'The wreck is fresh, the policy is not, and the other car is luxurious.', 96.3],

            // Social and event disasters
            ['Wedding Cake Collapses Before the Ceremony', 'Five decorated tiers fold into themselves in front of both families.', 65.1],
            ['Drop the Wedding Ring Down a Drain', 'It vanishes with a metallic click minutes before the ceremony.', 80.3],
            ['Your Trousers Split Open on Stage', 'The rip is loud, the spotlight is bright, and every camera is recording.', 54.8],
            ['Use the Wrong Name in a Wedding Speech', 'The room freezes when you congratulate the couple using an ex’s name.', 59.6],
            ['Lose the Proposal Ring in the Sea', 'One wave removes the ring seconds before the planned question.', 72.1],
            ['Throw a Surprise Party for the Wrong Person', 'The decorations reveal a celebration nobody in the room understands.', 56.8],
            ['Your Ex Sends Old Screenshots to Your Partner', 'Years of private messages arrive without context at the worst possible time.', 79.2],
            ['The Wedding Venue Locks Everyone Outside', 'Guests, flowers, and musicians wait in the rain while nobody answers.', 78.1],
            ['The DJ Plays Your Private Voice Note', 'A personal recording fills the speakers instead of the first-dance song.', 67.1],
            ['Power Fails at a Two-Hundred-Guest Reception', 'The music, lights, kitchen, and payment terminals all die together.', 74.3],

            // Health and public misery
            ['Break a Front Tooth on a First Date', 'One bite changes your smile and the rest of the evening.', 60.9],
            ['Get Food Poisoning on a Long Flight', 'The seatbelt sign stays on while your stomach declares an emergency.', 73.2],
            ['Have an Allergic Reaction at a Restaurant', 'Your face starts swelling while nobody can identify the ingredient.', 85.9],
            ['Twist Your Ankle on a Mountain Without Signal', 'The trail is steep, daylight is fading, and the car is hours away.', 90.8],
            ['Get Trapped in an Elevator for Twelve Hours', 'The emergency speaker promises help and then stops responding.', 71.9],
            ['Lose a Contact Lens Before a Live Interview', 'The studio lights blur while you pretend to recognize the host.', 56.2],
            ['Get a Nosebleed in a White Wedding Suit', 'The first bright red drop lands moments before the photographs.', 55.1],
            ['Sneeze While a Barber Holds a Razor', 'One badly timed movement turns a haircut into an urgent problem.', 63.6],
            ['Swallow a Dental Crown Before an Interview', 'Your smile changes and the appointment starts in ten minutes.', 61.2],
            ['Your Surgery Is Cancelled After Months of Waiting', 'You are already prepared when the hospital sends everyone home.', 69.9],

            // Nature and outdoor disasters
            ['Lightning Strikes Beside Your Tent', 'The flash and explosion arrive together in the middle of an open field.', 94.1],
            ['Your Tent Floods in the Night', 'Sleeping bags, clothes, and phones begin floating around you.', 74.6],
            ['A Bear Finds the Food Inside Your Campsite', 'The animal stands between you, the tent, and the only road out.', 92.8],
            ['Find a Snake in the Shower', 'The door closes behind you before the movement near the drain makes sense.', 76.8],
            ['A Bee Swarm Gets Trapped in Your Car', 'The doors are shut and suddenly every surface is buzzing.', 82.1],
            ['A Flash Flood Rushes Into the Canyon', 'The exit disappears as a wall of muddy water turns the bend.', 99.1],
            ['An Avalanche Blocks the Only Road', 'The cabin is isolated, supplies are low, and more snow is falling.', 91.7],
            ['A Ski Lift Stops in an Incoming Storm', 'You hang above the slope while wind and darkness grow stronger.', 88.3],
            ['Your Kayak Drifts Away From Shore', 'The current takes the boat, your bag, and the only dry clothes downstream.', 70.9],
            ['Boat Engine Dies as a Storm Arrives', 'The waves rise while the shoreline moves farther away.', 96.8],

            // Animal-powered disasters
            ['Your Dog Eats Your Passport', 'The evidence is scattered across the floor on the morning of your flight.', 63.2],
            ['Your Cat Deletes the Final Manuscript', 'One walk across the keyboard closes the file and confirms every prompt.', 59.1],
            ['Your Pet Escapes at the Airport', 'The carrier opens and your animal vanishes into a crowded terminal.', 86.9],
            ['A Skunk Sprays the Wedding Clothes', 'The outfits are untouched visually and impossible to wear.', 64.9],
            ['Your Dog Destroys the Wedding Cake', 'The reception centerpiece becomes an extremely happy animal’s dinner.', 58.2],
            ['Your Parrot Repeats a Secret at Family Dinner', 'It delivers the private sentence clearly enough for every relative to hear.', 57.8],
            ['A Giant Aquarium Breaks in Your Apartment', 'Water, glass, and fish spread across the floor above your neighbor.', 84.1],
            ['A Horse Throws You Into Mud Before a Ceremony', 'Your formal clothes and dignity land together in the deepest puddle.', 62.4],
            ['A Raccoon Gets Trapped in Your Kitchen', 'It panics, opens cabinets, and turns every object into a weapon.', 65.7],
            ['A Seagull Steals Your Car Keys', 'The bird carries them over the water while your locked car waits nearby.', 68.1],

            // Modern digital disasters
            ['Accidentally Livestream a Therapy Session', 'The viewer count rises before you notice the red LIVE indicator.', 87.1],
            ['Send a Private Photo to the Family Group', 'Read receipts appear faster than the delete option.', 83.2],
            ['Your Phone Dies in an Unfamiliar City', 'Tickets, directions, payment cards, and every contact vanish at once.', 63.9],
            ['Smart Lock Battery Dies in Heavy Rain', 'The app connects perfectly to a door that no longer has power.', 60.1],
            ['Your Drone Crashes Into a Stadium Scoreboard', 'The live broadcast follows it all the way down.', 75.1],
            ['Camera Card Corrupts After a Once-in-a-Lifetime Trip', 'Thousands of photographs become one unreadable error message.', 69.1],
            ['Autocorrect Sends a Resignation to Your Boss', 'Your casual complaint becomes a surprisingly formal final message.', 56.5],
            ['Order One Hundred Appliances by Mistake', 'Delivery trucks arrive before customer support answers.', 66.9],
            ['Home Automation Unlocks Every Door on Vacation', 'A security alert shows the house wide open from another country.', 85.1],
            ['Navigation Sends You Onto a Closed Mountain Road', 'The pavement ends, reversing is impossible, and snow starts falling.', 91.5],

            // Spectacular near-catastrophes
            ['Fireworks Ignite the Garden Shed', 'The first explosion inside reveals how much fuel and paint were stored there.', 89.4],
            ['A Gas Barbecue Erupts During the Party', 'A fireball replaces dinner while guests scatter across the garden.', 86.1],
            ['Fire Sprinklers Destroy an Art Exhibition', 'A false alarm soaks every piece minutes before opening night.', 81.1],
            ['A Chandelier Falls Into the Dinner Table', 'It lands where the guests were seated seconds earlier.', 95.1],
            ['Stage Pyrotechnics Set Your Costume on Fire', 'The audience applauds because they think the flames are part of the show.', 93.8],
            ['A Moving Truck Rolls Downhill', 'Everything you own accelerates toward a crowded intersection.', 97.8],
            ['A Crane Drops a Hot Tub Through Your Roof', 'The delivery arrives directly into the living room from above.', 98.8],
            ['A Sinkhole Swallows Your Driveway and Car', 'The ground opens overnight and leaves the vehicle at the bottom.', 94.7],
            ['Live Television Reveals Your Biggest Secret', 'The host reads the wrong card and millions hear what was meant to stay private.', 78.4],
            ['A Meteorite Punches Through Your Roof', 'The impossible rock misses you, destroys the house, and starts smoking.', 96.1],
        ];

        $spicyEvents = $this->expandPremiumEvents([
            ['A Cow Bites You on the Butt', 'You only wanted a photo; the cow chose violence.'],
            ['A Goat Eats Half Your Trousers', 'It keeps chewing while you negotiate for the part you are still wearing.'],
            ['A Porta-Potty Rolls Downhill With You Inside', 'Every bump makes the locked door feel less reassuring.'],
            ['A Raccoon Steals Your Underwear While You Swim', 'It carries the only dry pair into the woods like a trophy.'],
            ['A Goose Chases You Through a Funeral', 'Respectful silence ends when the bird chooses you as its enemy.'],
            ['Your Dentures Fly Into Someone’s Soup', 'The table watches them sink beneath the noodles.'],
            ['A Monkey Throws Your Phone Into a Volcano', 'It makes eye contact before releasing your entire digital life.'],
            ['A Seagull Poops Directly Into Your Open Mouth', 'You looked up at exactly the wrong second.'],
            ['Your Wig Lands on the Wedding Cake', 'The photographer captures the exact moment it becomes decoration.'],
            ['A Horse Sneezes Into Your Face', 'The spray reaches places you did not know needed washing.'],
            ['A Skunk Sprays You Inside a Rental Car', 'The windows are closed and the security deposit is already gone.'],
            ['A Llama Spits During Your Job Interview', 'The interviewer stays perfectly dry and continues asking questions.'],
            ['A Rooster Attacks Your Bare Legs', 'The shortcut across the farm becomes a full sprint for survival.'],
            ['Your Hammock Snaps Above a Mud Pit', 'For one peaceful second you are floating; then you are seasoning the mud.'],
            ['A Parade Float Drives Away With You on the Toilet', 'The wall opens and the crowd assumes you are part of the show.'],
            ['Your Inflatable Boat Deflates in a Duck Pond', 'The ducks gather to watch you slowly become part of the pond.'],
            ['A Squirrel Hides Acorns in Your Wedding Dress', 'They begin falling out halfway down the aisle.'],
            ['Your False Eyelash Glues Both Eyes Shut', 'Everyone thinks you are being dramatic until you hit the wall.'],
            ['A Pig Steals Your Picnic and Your Car Keys', 'It eats lunch first and then disappears with your way home.'],
            ['Your Chair Collapses During a Silent Ceremony', 'The crack echoes longer than the speech that follows.'],
            ['A Pelican Swallows Your Microphone', 'The speech continues from somewhere inside the bird.'],
            ['Your Tanning Spray Turns Bright Green', 'The bottle promised bronze and delivered swamp creature.'],
            ['A Donkey Brays Through Your Entire Proposal', 'Every romantic sentence is answered with a louder opinion.'],
            ['A Crab Refuses to Let Go of Your Toe', 'You cross the beach with a new accessory and no dignity.'],
            ['A Circus Clown Gets Trapped in Your Chimney', 'The noises are terrifying until a red shoe drops into the fireplace.'],
        ], 'spicy');

        $adultEvents = $this->expandPremiumEvents([
            ['Your Parents Walk In During Sex', 'The door opens before either of you can reach the sheets.'],
            ['Send a Nude to the Family Group Chat', 'Read receipts appear before you can find the delete button.'],
            ['The Condom Breaks During a One-Night Stand', 'The mood disappears instantly and neither of you knows what comes next.'],
            ['Say Your Ex’s Name During Sex', 'The room goes silent while both of you process exactly what you said.'],
            ['Your Intimate Video Plays on the Office Screen', 'The presentation connects to the wrong file while the entire team watches.'],
            ['A Sex Toy Falls Out of Your Bag at Security', 'The scanner operator raises it while the entire queue watches.'],
            ['Your Partner Finds Your Secret Dating Profile', 'The active status makes every explanation sound worse.'],
            ['Your Nude Selfie Syncs to the Family Television', 'The slideshow changes before anyone can find the remote.'],
            ['The Hotel Cleaner Walks In Mid-Position', 'The do-not-disturb sign is still hanging safely inside the room.'],
            ['You Moan the Wrong Name Loud Enough for the Neighbors', 'The wall goes quiet and your partner asks one very specific question.'],
            ['Your Handcuff Key Breaks in the Lock', 'The playful experiment becomes an awkward call for professional help.'],
            ['Your Boss Receives Your Sext Instead of Your Partner', 'The typing indicator appears and then disappears for a very long time.'],
            ['Your Bedroom Livestream Accidentally Goes Public', 'The viewer count climbs before you notice the red icon.'],
            ['A Condom Falls Out During a Family Dinner', 'It lands in the middle of the table between the bread and your grandmother.'],
            ['Your Partner’s Parent Finds You Naked in the Kitchen', 'You were looking for water and find the entire family instead.'],
            ['Your Dirty Talk Activates the Smart Speaker', 'The recording is saved and sent to the shared household account.'],
            ['A Neighbor Returns Your Vibrating Package', 'They explain exactly how long it has been making noise.'],
            ['You Get Stuck in a Compromising Position', 'The cramp arrives first and the emergency call becomes unavoidable.'],
            ['Your Ex Sends Intimate Screenshots to Your New Partner', 'Years of private messages arrive without context or warning.'],
            ['The Bed Collapses Through the Apartment Floor', 'The neighbors downstairs discover far more than the structural damage.'],
            ['Your Waxing Appointment Removes Much More Than Planned', 'The mirror confirms that the requested shape is no longer possible.'],
            ['Your Date Discovers the Matching Tattoo With Your Ex', 'The lights are already off when they recognize the initials.'],
            ['Your Partner’s Name Is Tattooed in the Wrong Place', 'The artist reveals the spelling mistake after the final wipe.'],
            ['A Private Voice Note Plays Through the Bar Speakers', 'Your detailed plans replace the music for everyone in the room.'],
            ['The Fire Alarm Starts While You Are Both Naked', 'The entire hotel waits outside while you share one tiny towel.'],
        ], 'adult');

        // Explicitly ranked after comparing the consequence described on every card.
        // The order moves from embarrassment/inconvenience through lasting loss and
        // financial damage to situations with immediate risk to life.
        $severityOrder = [
            // 00–19: recoverable embarrassment, inconvenience, or professional disruption.
            'Throw a Surprise Party for the Wrong Person',
            'Get a Nosebleed in a White Wedding Suit',
            'A Horse Throws You Into Mud Before a Ceremony',
            'Your Card Is Declined at an Anniversary Dinner',
            'Smart Lock Battery Dies in Heavy Rain',
            'Your Trousers Split Open on Stage',
            'Lose a Contact Lens Before a Live Interview',
            'Use the Wrong Name in a Wedding Speech',
            'Your Dog Destroys the Wedding Cake',
            'Wedding Cake Collapses Before the Ceremony',
            'A Skunk Sprays the Wedding Clothes',
            'Find a Snake in the Shower',
            'Send a Complaint About Someone Directly to Them',
            'Send a Private Screenshot Back to Its Subject',
            'Your Parrot Repeats a Secret at Family Dinner',
            'Order One Hundred Appliances by Mistake',
            'The DJ Plays Your Private Voice Note',
            'Your Microphone Stays Live While Insulting a Client',
            'Autocorrect Sends a Resignation to Your Boss',
            'Computer Crashes During a Live Keynote',
            // 20–39: physical mishaps, replaceable loss, or major travel/event disruption.
            'Swallow a Dental Crown Before an Interview',
            'Break a Front Tooth on a First Date',
            'The Refrigerator Dies While You Are Away',
            'A Raccoon Gets Trapped in Your Kitchen',
            'Hotel Loses Your Reservation During a Festival',
            'Your Dog Eats Your Passport',
            'A Seagull Steals Your Car Keys',
            'Arrive at the Wrong Airport',
            'Miss the Last Ferry Off the Island',
            'Your Phone Dies in an Unfamiliar City',
            'The Wedding Venue Locks Everyone Outside',
            'Power Fails at a Two-Hundred-Guest Reception',
            'Your Kayak Drifts Away From Shore',
            'Wash an Envelope Full of Cash',
            'Lose the Proposal Ring in the Sea',
            'Drop the Wedding Ring Down a Drain',
            'Get Food Poisoning on a Long Flight',
            'Rental Car Gets Towed Abroad',
            'Lose Your Wallet in a Foreign Country',
            'Lose Your Passport at the Boarding Gate',
            // 40–59: property damage, irreplaceable loss, or serious health/access problems.
            'Sewage Backs Up During a Dinner Party',
            'A Pipe Bursts Above Your Bedroom',
            'Your Tent Floods in the Night',
            'Air Conditioning Fails in a Record Heatwave',
            'Laptop Is Stolen Before the Big Presentation',
            'Email the Salary Spreadsheet to the Whole Company',
            'Your Ex Sends Old Screenshots to Your Partner',
            'Send a Private Photo to the Family Group',
            'Camera Card Corrupts After a Once-in-a-Lifetime Trip',
            'Cloud Sync Overwrites Every Family Photo',
            'Your Cat Deletes the Final Manuscript',
            'Delete Your Finished Thesis Without a Backup',
            'Home Automation Unlocks Every Door on Vacation',
            'A Giant Aquarium Breaks in Your Apartment',
            'Your Drone Crashes Into a Stadium Scoreboard',
            'A Chandelier Falls Into the Dinner Table',
            'Your Pet Escapes at the Airport',
            'Your Surgery Is Cancelled After Months of Waiting',
            'Airline Loses the Bag With Your Medicine',
            'Bank Freezes Your Accounts While You Are Abroad',
            // 60–79: prolonged danger, major privacy/financial harm, or catastrophic property loss.
            'Sneeze While a Barber Holds a Razor',
            'Get Trapped in an Elevator for Twelve Hours',
            'Live Television Reveals Your Biggest Secret',
            'Accidentally Livestream a Therapy Session',
            'Accidentally Win an Auction You Cannot Afford',
            'Get Audited After Losing Every Receipt',
            'Basement Floods With Every Family Album',
            'Fire Sprinklers Destroy an Art Exhibition',
            'Ceiling Collapses During Dinner',
            'A Bee Swarm Gets Trapped in Your Car',
            'Heating Fails During a Blizzard',
            'Ransomware Locks Every Company File',
            'Erase the Production Database',
            'Discover Someone Stole Your Identity',
            'Payroll Sends Everyone Double From Your Account',
            'A Tree Crushes Your Car and Garage',
            'A Sinkhole Swallows Your Driveway and Car',
            'A Crane Drops a Hot Tub Through Your Roof',
            'A Storm Tears Open Your Roof',
            'Total Your Car the Day Insurance Expires',
            // 80–99: devastating financial loss, acute life danger, or an active catastrophe.
            'Wire Your House Deposit to the Wrong Account',
            'A Meteorite Punches Through Your Roof',
            'Run Out of Fuel in the Desert',
            'An Avalanche Blocks the Only Road',
            'Twist Your Ankle on a Mountain Without Signal',
            'Navigation Sends You Onto a Closed Mountain Road',
            'Windshield Shatters While Driving',
            'Engine Dies Inside a Long Tunnel',
            'Tire Explodes on a Busy Highway',
            'A Gas Barbecue Erupts During the Party',
            'A Ski Lift Stops in an Incoming Storm',
            'An Oven Fire Takes Over the Kitchen',
            'Have an Allergic Reaction at a Restaurant',
            'Fireworks Ignite the Garden Shed',
            'A Bear Finds the Food Inside Your Campsite',
            'Lightning Strikes Beside Your Tent',
            'Stage Pyrotechnics Set Your Costume on Fire',
            'A Moving Truck Rolls Downhill',
            'Boat Engine Dies as a Storm Arrives',
            'A Flash Flood Rushes Into the Canyon',
        ];

        $eventTitles = array_column($events, 0);
        if (count($severityOrder) !== count($events)
            || count(array_unique($severityOrder)) !== count($severityOrder)
            || array_diff($eventTitles, $severityOrder)
            || array_diff($severityOrder, $eventTitles)) {
            throw new \LogicException('Card severity order must contain every seeded card exactly once.');
        }

        $rankByTitle = array_flip($severityOrder);
        $events = array_map(static function (array $event) use ($rankByTitle): array {
            $rank = $rankByTitle[$event[0]];
            $hundredth = $rank === 99 ? 99 : abs(crc32($event[0])) % 99 + 1;
            $event[2] = $rank + ($hundredth / 100);

            return $event;
        }, $events);

        if ($this->scoresOnly) {
            DB::transaction(function () use ($events) {
                foreach ($events as $event) {
                    Card::query()->where('title', $event[0])->update(['score' => $event[2]]);
                }
            });

            return;
        }

        DB::transaction(function () use ($adult, $adultEvents, $events, $normal, $spicy, $spicyEvents) {
            $seedPack = static function (Stack $stack, string $deck, array $packEvents): void {
                foreach ($packEvents as $event) {
                    Card::query()->updateOrCreate(
                        ['title' => $event[0], 'stack_id' => $stack->id],
                        [
                            'subtitle' => $event[1],
                            'score' => $event[2],
                            'deck' => $deck,
                            'status' => true,
                        ],
                    );
                }
            };

            // Artwork, translations, games and runtime rows are user data. A content
            // seed may update the seeded copy and score, but must never delete them.
            $seedPack($normal, 'normal', $events);
            $seedPack($adult, '18-plus', $adultEvents);
            $seedPack($spicy, 'spicy', $spicyEvents);
        });
    }

    private function expandPremiumEvents(array $events, string $tone): array
    {
        $variants = $tone === 'adult'
            ? [
                ['', ''],
                [' — At Your Partner’s Parents’ House', 'To make it worse, their parents are close enough to hear everything.'],
                [' — During a Video Call', 'The camera is on, the microphone is live, and disconnecting takes far too long.'],
                [' — On Your First Night Together', 'Neither of you knows whether laughing will make the situation better or worse.'],
            ]
            : [
                ['', ''],
                [' — In Front of Everyone', 'A full crowd gets an unobstructed view of the disaster.'],
                [' — While Being Livestreamed', 'The viewer count rises while the situation keeps getting worse.'],
                [' — Five Minutes Before a Big Event', 'There is no time to clean up, change clothes, or invent a believable excuse.'],
            ];

        $expanded = [];
        foreach ($events as $eventIndex => $event) {
            foreach ($variants as $variantIndex => $variant) {
                $position = $eventIndex * count($variants) + $variantIndex;
                $title = $event[0].$variant[0];
                $hundredth = $position === 99 ? 99 : abs(crc32($title)) % 99 + 1;
                $expanded[] = [
                    $title,
                    trim($event[1].' '.$variant[1]),
                    $position + ($hundredth / 100),
                ];
            }
        }

        return $expanded;
    }
}
