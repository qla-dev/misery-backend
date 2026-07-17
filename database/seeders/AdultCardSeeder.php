<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Stack;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdultCardSeeder extends Seeder
{
    public function run(): void
    {
        $adult = Stack::updateOrCreate(
            ['slug' => '18-plus'],
            [
                'name' => '18+',
                'color' => '#ef4444',
                'icon_key' => 'shield-alert',
                'description' => 'Suggestive adult situations without graphic content',
                'description_bs' => 'Sugestivne situacije za odrasle bez eksplicitnog sadržaja',
            ],
        );

        $cards = [
            ['Your Parents Walk In While You Are Having Sex', 'The door opens and nobody remembers how doors, sheets, or eye contact work.', 82.41],
            ['You Send a Nude to the Family Group Chat', 'Three generations see it before the delete option appears.', 91.24],
            ['The Condom Breaks During a One-Night Stand', 'The mood vanishes and an extremely serious conversation begins.', 88.37],
            ['You Say Your Ex’s Name During Sex', 'The room goes silent except for one person asking you to repeat yourself.', 86.52],
            ['Your Sext Appears on the Office Presentation Screen', 'The projector reveals your evening plans to the entire department.', 94.18],
            ['A Sex Toy Falls Out of Your Bag at Airport Security', 'The officer lifts it for inspection while the queue stops pretending not to stare.', 67.73],
            ['Your Partner Finds Your Active Dating Profile', 'The “online now” label destroys every explanation before it starts.', 89.46],
            ['Your Nude Selfie Syncs to the Family Television', 'The holiday slideshow changes before anyone can reach the remote.', 93.05],
            ['The Hotel Cleaner Walks In While You Are Having Sex', 'The do-not-disturb sign is still hanging safely inside the room.', 71.62],
            ['Your Handcuff Key Snaps in the Lock', 'A playful idea becomes an humiliating call for professional help.', 76.29],
            ['Your Boss Receives Your Dirty Voice Note', 'The typing indicator appears, disappears, and never returns.', 92.67],
            ['Your Bedroom Livestream Accidentally Goes Public', 'The viewer count climbs before you notice the red icon.', 98.11],
            ['A Condom Falls Onto the Table at Family Dinner', 'It lands between the bread basket and your grandmother’s plate.', 54.84],
            ['Your Partner’s Father Finds You Naked in the Kitchen', 'You wanted a glass of water and discover the whole family is awake.', 74.33],
            ['Your Dirty Talk Activates the Smart Speaker', 'The recording is saved to the shared household account.', 69.91],
            ['A Neighbor Returns Your Loudly Vibrating Package', 'They explain exactly how long it has been making that noise.', 58.76],
            ['A Cramp Leaves You Stuck in a Compromising Position', 'Neither of you can move without making the situation considerably worse.', 47.38],
            ['Your Ex Sends Intimate Screenshots to Your New Partner', 'Years of private messages arrive without warning or context.', 90.43],
            ['The Bed Collapses Into the Apartment Below', 'The downstairs neighbors learn what happened before the landlord does.', 79.16],
            ['The Hotel Fire Alarm Catches You Both Naked', 'The entire building waits outside while you share one hand towel.', 73.58],
            ['Your Partner Discovers Your Secret Fetish Search History', 'The browser restores every tab during an innocent movie night.', 81.04],
            ['You Develop a Hickey Before Your Wedding Photos', 'Makeup fails and both families begin asking identical questions.', 62.87],
            ['Your Lingerie Delivery Goes to the Retired Couple Next Door', 'They return it opened because they were checking whose size it was.', 51.39],
            ['You Match With Your Partner’s Sibling on a Hookup App', 'Both phones announce the match at the same family gathering.', 77.22],
            ['Your Intimate Photo Is Printed With the Vacation Pictures', 'The clerk hands the stack to your mother to look through first.', 92.36],
            ['Your Date’s Dentures Come Out During a Passionate Kiss', 'They land in your drink and neither of you knows the correct etiquette.', 45.19],
            ['You Discover the Bedroom Mirror Is a Live Security Camera', 'The owner messages to ask why the motion alerts will not stop.', 97.64],
            ['Your Partner Finds the Sex Toy Named After Their Best Friend', 'The product nickname suddenly requires a very convincing history lesson.', 78.49],
            ['The Romantic Massage Oil Is Actually Hot Sauce', 'The label confusion becomes obvious several seconds too late.', 64.72],
            ['Your Birth Control Reminder Rings During a Silent Funeral', 'The custom notification announces its purpose to every mourner.', 57.81],
            ['Your Mother Opens Your Adult Subscription Box', 'She signs for it, opens it, and calls to discuss every item.', 75.06],
            ['You Find Your Partner’s Secret OnlyFans Account', 'You recognize the bedroom before you recognize the username.', 91.53],
            ['Your First-Time Roleplay Costume Splits Immediately', 'The dramatic entrance ends with a seam giving up completely.', 43.68],
            ['You Accidentally AirDrop a Nude to Everyone on the Train', 'A dozen phones display the same preview while people look around.', 95.27],
            ['Your Partner’s Smartwatch Displays Your Sext During a Meeting', 'They are presenting when your message fills the conference-room screen.', 87.14],
            ['You Buy Condoms From Your Former Teacher', 'They are covering the pharmacy register and remember your full name.', 49.82],
            ['Your Date Reveals They Once Slept With Your Boss', 'They mention it casually while you are already undressed.', 83.76],
            ['A Drone Appears Outside Your Bedroom Window', 'It hovers, records, and flies away before you can cover the glass.', 96.08],
            ['Your Partner Reads Your Erotic Fan Fiction Aloud', 'They find the folder and perform your worst paragraph with voices.', 66.45],
            ['You Leave Your Underwear in a Taxi', 'The driver posts a photo in the neighborhood lost-and-found group.', 52.31],
            ['Your Romantic Hotel Room Has Two Separate Single Beds', 'The nonrefundable “honeymoon suite” has a concrete wall between them.', 38.94],
            ['You Get Food Poisoning During a Sex Date', 'Your carefully planned night becomes a competition for the bathroom.', 72.69],
            ['Your Partner’s Name Is Misspelled in an Intimate Tattoo', 'The artist reveals the extra letter only after the final wipe.', 85.32],
            ['Your Ex’s Name Is Still Visible Under Your Lingerie', 'Your date notices the old tattoo at the exact wrong moment.', 74.57],
            ['The Neighbors File a Noise Complaint About Your Sex Life', 'The official letter includes dates, times, and disturbingly accurate notes.', 63.25],
            ['Your Bedroom Playlist Connects to the Wedding Speakers', 'A very specific collection of songs interrupts the first dance.', 68.13],
            ['You Learn Your Date Is Married From Their Bedroom Photo', 'The family portrait faces the bed and answers every question.', 92.74],
            ['Your Condom Is Three Years Past Its Expiration Date', 'You notice the tiny date only after the package is already open.', 80.26],
            ['Your Partner Is Allergic to Your Flavored Lubricant', 'The romantic experiment ends with swelling and an emergency pharmacy run.', 76.91],
            ['You Accidentally Call Your Mother During Sex', 'The call lasts long enough for her to leave a concerned voicemail.', 89.12],
            ['Your Intimate Video Autoplays at Full Volume in Public', 'Your headphones disconnect in the quietest carriage on the train.', 88.54],
            ['You Find Your Roommate Hiding Under the Bed', 'They were retrieving a charger and became too embarrassed to come out.', 84.63],
            ['The New Mattress Arrives During Your One-Night Stand', 'The delivery crew removes the old bed while your date waits in a towel.', 55.47],
            ['You Lose a Sex Toy Inside a Hotel Room', 'Housekeeping calls after checkout to describe the item they found.', 61.83],
            ['Your Partner’s Child Finds the Hidden Condom Drawer', 'They bring the colorful packages to breakfast and ask what balloons they are.', 70.38],
            ['You Discover Your Date Is Your Therapist’s Ex', 'The recognition happens halfway through an intimate confession.', 82.09],
            ['Your Waxing Appointment Is With Your School Rival', 'They recognize you immediately and insist on making conversation.', 59.64],
            ['A Romantic Candle Sets the Lingerie on Fire', 'The seduction ends with a fire extinguisher and one ruined carpet.', 77.48],
            ['Your Partner Finds Your List Ranking Every Ex', 'The spreadsheet includes scores, notes, and a column for bedroom chemistry.', 93.61],
            ['You Receive Someone Else’s STI Test Results', 'The clinic calls after you have already shown them to your partner.', 86.07],
            ['Your Own STI Test Is Emailed to Your Work Account', 'The attachment appears in the company security review queue.', 90.86],
            ['The Condom Machine Swallows Your Last Money', 'It is midnight, every shop is closed, and your date is waiting upstairs.', 41.75],
            ['You Meet Your Gynecologist on a Hookup App', 'They swipe right and open with “small world.”', 71.44],
            ['Your Date Has Your Parent’s Name Tattooed on Their Chest', 'They explain the coincidence after you have already reached the bedroom.', 56.92],
            ['Your Partner Finds a Stranger’s Underwear in Your Bed', 'You have no idea how it got there, which sounds exactly like a lie.', 94.37],
            ['Your Sex Toy Connects to the Neighbor’s Bluetooth', 'Their music stops and your device begins responding to their controls.', 73.26],
            ['The Hotel Television Displays Your Adult Purchase History', 'The checkout screen itemizes everything while reception waits.', 65.59],
            ['You Get Locked Naked on the Balcony', 'The door slides shut and the only person with a spare key is your landlord.', 78.03],
            ['Your Partner’s Grandmother Finds Your Lingerie in the Laundry', 'She folds it neatly and asks whose tiny outfit it is.', 53.86],
            ['You Fall Asleep During Your Partner’s Seduction', 'You wake up alone beside candles that have burned themselves out.', 60.24],
            ['Your Date Laughs at Your Bedroom Safe Word', 'It is also the name of their childhood dog.', 48.67],
            ['Your Romantic Roleplay Is Interrupted by the Police', 'A neighbor mistakes the performance for a real emergency.', 87.93],
            ['You Discover the Motel Room Is Being Rented by the Hour', 'The receptionist announces the pricing options in front of your in-laws.', 50.18],
            ['Your Partner Posts a Post-Sex Selfie With You Visible', 'The mirror reveals far more than either of you noticed.', 90.21],
            ['You Find an Engagement Ring During a One-Night Stand', 'It is hidden beside the bed and definitely is not meant for you.', 69.04],
            ['Your Date Confesses They Are a Virgin After Claiming Otherwise', 'The truth arrives after an evening of increasingly impossible stories.', 46.82],
            ['Your Partner’s Ex Still Has a Key to the Bedroom', 'They use it without knocking at the worst possible moment.', 88.39],
            ['The Aphrodisiac Chocolate Is Actually a Strong Laxative', 'The romantic label was attached to the wrong handmade box.', 71.18],
            ['Your New Piercing Gets Caught in the Bedsheets', 'The attempted escape turns into a delicate rescue operation.', 63.74],
            ['Your Intimate Polaroid Develops in Front of the Wrong Person', 'You hand it to a waiter thinking it is the restaurant receipt.', 84.15],
            ['You Discover Your Partner Has Been Faking Every Orgasm', 'They admit it during an argument and cannot take the sentence back.', 89.72],
            ['Your Date Requests a Safe Word You Use at Work', 'Every future meeting will now remind you of this conversation.', 44.36],
            ['Your Condom Purchase Appears on the Shared Family Account', 'The notification includes the store, time, and loyalty points earned.', 58.09],
            ['You Get a Hickey Shaped Like a Country', 'Your coworkers spend the morning debating which nation is on your neck.', 51.67],
            ['Your Partner Finds Your Hidden Lingerie Receipt', 'The size, color, and delivery date match absolutely nothing they own.', 91.38],
            ['The Sex Shop Clerk Is Your Partner’s Best Friend', 'They offer a loyalty discount and promise they can keep a secret.', 62.46],
            ['Your Date Brings Their Parent as a Safety Chaperone', 'The parent waits in the next room and asks when you will be finished.', 57.28],
            ['Your Bedroom Ceiling Fan Destroys the Romantic Setup', 'Rose petals, underwear, and one expensive toy scatter through the open window.', 49.61],
            ['You Discover Your Partner Recorded You Without Permission', 'A blinking storage notification reveals the hidden phone.', 99.02],
            ['Your Sexy Costume Is Identical to the Waiter’s Uniform', 'Your grand entrance ends when everyone asks you for another drink.', 42.79],
            ['You Wake Up Wearing Someone Else’s Underwear', 'Neither you nor your date recognizes it.', 73.97],
            ['Your Landlord Schedules a Bedroom Inspection Without Warning', 'They arrive with a clipboard while every adult item is still in view.', 68.82],
            ['Your Partner’s Fitness App Records the Entire Encounter', 'The shared workout feed publishes the duration, heart rate, and location.', 79.55],
            ['You Discover Your Date Has a Sex Doll That Looks Like You', 'The resemblance is exact enough to eliminate the word coincidence.', 87.31],
            ['Your Underwear Tears Off With the Price Tag Still Attached', 'Your date notices both the dramatic rip and the discount sticker.', 40.53],
            ['You Accidentally Use Permanent Body Paint for Foreplay', 'The label promises seven days before the color begins to fade.', 66.98],
            ['Your Partner’s Wedding Ring Appears Mid-Date', 'They claimed to be single until it rolls out from under the bed.', 95.49],
            ['A Parrot Repeats Your Dirty Talk at Breakfast', 'It performs the entire vocabulary for visiting relatives.', 70.65],
            ['Your Date Leaves a Dental Retainer in Your Bed', 'You find it with your foot after they have already gone home.', 37.84],
            ['The Bedroom Door Falls Off Its Hinges', 'It crashes into the hallway and removes every remaining trace of privacy.', 72.57],
        ];

        // Headlines read more naturally as event names when the subject leads.
        // Avoid the repetitive generated-sounding "Your ..." opening while the
        // descriptions continue addressing the player directly.
        $youTitleRewrites = [
            'You Send a Nude to the Family Group Chat' => 'A Nude Lands in the Family Group Chat',
            'You Say Your Ex’s Name During Sex' => 'The Wrong Name Comes Out During Sex',
            'You Develop a Hickey Before Your Wedding Photos' => 'A Fresh Hickey Ruins the Wedding Photos',
            'You Match With Your Partner’s Sibling on a Hookup App' => 'A Hookup App Matches You With Your Partner’s Sibling',
            'You Discover the Bedroom Mirror Is a Live Security Camera' => 'The Bedroom Mirror Turns Out to Be a Live Security Camera',
            'You Find Your Partner’s Secret OnlyFans Account' => 'A Secret OnlyFans Account Uses Your Bedroom as Its Set',
            'You Accidentally AirDrop a Nude to Everyone on the Train' => 'An Accidental AirDrop Sends Your Nude Across the Train',
            'You Buy Condoms From Your Former Teacher' => 'A Former Teacher Rings Up Your Condoms',
            'You Leave Your Underwear in a Taxi' => 'Underwear Left in a Taxi Appears Online',
            'You Get Food Poisoning During a Sex Date' => 'Food Poisoning Interrupts a Sex Date',
            'You Learn Your Date Is Married From Their Bedroom Photo' => 'A Bedroom Photo Reveals That Your Date Is Married',
            'You Accidentally Call Your Mother During Sex' => 'An Accidental Call Lets Your Mother Hear Everything',
            'You Find Your Roommate Hiding Under the Bed' => 'A Roommate Is Hiding Under the Bed',
            'You Lose a Sex Toy Inside a Hotel Room' => 'Housekeeping Finds the Sex Toy You Left Behind',
            'You Discover Your Date Is Your Therapist’s Ex' => 'Tonight’s Date Turns Out to Be Your Therapist’s Ex',
            'You Receive Someone Else’s STI Test Results' => 'The Clinic Sends You Someone Else’s STI Results',
            'You Meet Your Gynecologist on a Hookup App' => 'A Hookup App Matches You With Your Gynecologist',
            'You Get Locked Naked on the Balcony' => 'The Balcony Door Locks While You Are Naked',
            'You Fall Asleep During Your Partner’s Seduction' => 'Falling Asleep Ends Your Partner’s Seduction',
            'You Discover the Motel Room Is Being Rented by the Hour' => 'The Motel Announces Its Hourly Rate in Front of Your In-Laws',
            'You Find an Engagement Ring During a One-Night Stand' => 'An Engagement Ring Appears During a One-Night Stand',
            'You Discover Your Partner Has Been Faking Every Orgasm' => 'Partner Admits Every Orgasm Was Fake',
            'You Get a Hickey Shaped Like a Country' => 'A Country-Shaped Hickey Starts an Office Debate',
            'You Discover Your Partner Recorded You Without Permission' => 'A Hidden Phone Records You Without Permission',
            'You Wake Up Wearing Someone Else’s Underwear' => 'Someone Else’s Underwear Is on You the Next Morning',
            'You Discover Your Date Has a Sex Doll That Looks Like You' => 'Date Owns a Sex Doll That Looks Exactly Like You',
            'You Accidentally Use Permanent Body Paint for Foreplay' => 'Permanent Body Paint Gets Mistaken for Foreplay Paint',
        ];

        $cards = array_map(static function (array $card) use ($youTitleRewrites): array {
            $card[0] = $youTitleRewrites[$card[0]] ?? $card[0];
            if (str_starts_with($card[0], 'Your Own ')) {
                $card[0] = 'An '.substr($card[0], strlen('Your Own '));
            } elseif (str_starts_with($card[0], 'Your ')) {
                $card[0] = substr($card[0], strlen('Your '));
            }

            return $card;
        }, $cards);

        if (count($cards) !== 100 || count(array_unique(array_column($cards, 0))) !== 100) {
            throw new \LogicException('The 18+ deck must contain exactly 100 uniquely titled cards.');
        }

        DB::transaction(function () use ($adult, $cards): void {
            Card::query()
                ->where('stack_id', $adult->id)
                ->orWhere('deck', '18-plus')
                ->delete();

            foreach ($cards as [$title, $subtitle, $score]) {
                Card::query()->create([
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'score' => $score,
                    'image' => '0',
                    'deck' => '18-plus',
                    'stack_id' => $adult->id,
                    'status' => true,
                ]);
            }
        });
    }
}
