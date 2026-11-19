<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Adds landscaping/gardening, domestic help, pest control, vets, car detailing and building to the catalogue,
 * plus gas refill/installation services. Idempotent and never overwrites a service an admin already edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        // [name, icon, short, emergency, licence required, licence hint, [[service, callout, min, max, description]]]
        $new = [
            ['Landscaping & Gardening', 'leaf', 'Landscapers, gardeners and lawn care for homes, offices and estates.', false, false, 'Any horticulture or landscaping certificate, if you have one.', [
                ['Garden design and landscaping', 0, 1500, 40000, 'Design and build lawns, flower beds, paving and outdoor spaces.'],
                ['Lawn mowing and lawn care', 0, 150, 1200, 'Mowing, edging, feeding, weeding and re-seeding of lawns.'],
                ['Gardener (regular visits)', 0, 150, 800, 'Weekly or monthly garden upkeep: watering, weeding, planting and clean-up.'],
                ['Tree cutting and pruning', 200, 300, 5000, 'Tree felling, trimming and branch removal, with rubbish taken away.'],
                ['Hedge trimming', 0, 150, 1500, 'Shaping and trimming hedges and shrubs.'],
                ['Irrigation and sprinkler installation', 200, 500, 12000, 'Sprinkler systems, drip lines and garden water points.'],
                ['Garden clearing and waste removal', 150, 250, 2500, 'Clearing overgrown plots and removing garden waste.'],
            ]],
            ['Domestic Help', 'home', 'House helps, nannies and cleaners for your home.', false, false, 'References or a police clearance help customers trust you.', [
                ['House help (daily)', 0, 150, 500, 'A trusted helper for cleaning, laundry and cooking, by the day.'],
                ['House help (live-in or monthly)', 0, 1200, 4000, 'Regular monthly house help. Agree duties and pay with the customer.'],
                ['Nanny and childcare', 0, 200, 700, 'Childminding at home, by the day or evening.'],
                ['Laundry and ironing', 0, 100, 500, 'Washing, drying and ironing collected or done at your home.'],
                ['Deep house cleaning', 0, 300, 2500, 'Top-to-bottom cleaning, including after parties or before moving in.'],
                ['Cook / catering help', 0, 200, 1500, 'A cook for the day or for an event at your home.'],
            ]],
            ['Pest Control & Extermination', 'bug', 'Exterminators for cockroaches, termites, rodents, mosquitoes and more.', true, true, 'Pest control or pesticide-handling certificate, or registration with the relevant authority.', [
                ['Cockroach and ant extermination', 150, 300, 1500, 'Treatment of kitchens and homes for cockroaches, ants and crawling insects.'],
                ['Termite treatment', 250, 800, 8000, 'Inspection and treatment of termites in buildings and roof timber.'],
                ['Rodent control', 150, 300, 2000, 'Rats and mice removed and entry points sealed.'],
                ['Bed bug and flea treatment', 150, 400, 2500, 'Treatment of beds, furniture and rooms.'],
                ['Mosquito and fly control', 150, 300, 3000, 'Spraying of yards and rooms, drain and stagnant water treatment.'],
                ['Snake and wildlife removal', 200, 300, 2000, 'Safe removal of snakes, bats and other wildlife from homes.'],
                ['Fumigation of premises', 250, 800, 10000, 'Whole-building fumigation for homes, offices, shops and warehouses.'],
            ]],
            ['Veterinary Services', 'paw', 'Vets for pets and livestock: emergencies, vaccinations and home visits.', true, true, 'Veterinary registration or practising certificate.', [
                ['Emergency vet call-out', 300, 400, 3000, 'Urgent attention for injured or very sick pets and animals.'],
                ['Pet vaccination', 100, 150, 600, 'Vaccinations and boosters for dogs and cats, at home.'],
                ['Pet check-up and treatment', 150, 200, 1500, 'Examination, diagnosis and treatment for pets.'],
                ['Livestock treatment', 300, 400, 5000, 'Cattle, goats, pigs and poultry: treatment, dosing and herd health.'],
                ['Pet grooming', 0, 150, 700, 'Bathing, brushing and trimming at home.'],
                ['Spaying and neutering', 200, 400, 1500, 'Routine surgery by a registered vet.'],
                ['Pet transport and boarding help', 100, 150, 800, 'Moving a pet to a clinic, or arranging short-term boarding.'],
            ]],
            ['Car Detailing & Cleaning', 'sparkles', 'Mobile car wash, detailing and valeting that comes to you.', false, false, 'Not required.', [
                ['Mobile car wash', 0, 80, 250, 'Exterior wash and dry at your home or office.'],
                ['Interior cleaning and valet', 0, 150, 600, 'Vacuuming, seat and carpet cleaning, dashboard and windows.'],
                ['Full car detailing', 0, 400, 2500, 'Deep clean inside and out, clay bar and wax.'],
                ['Polishing and paint correction', 0, 500, 4000, 'Machine polish to remove swirls and restore shine.'],
                ['Engine bay cleaning', 0, 150, 500, 'Degrease and clean the engine compartment.'],
                ['Upholstery and carpet shampoo', 0, 250, 1200, 'Stain and odour removal from seats and carpets.'],
                ['Fleet and bus washing', 0, 300, 5000, 'Regular washing for company vehicles, taxis and buses.'],
            ]],
            ['Building & Construction', 'building', 'Builders, bricklayers, tilers and renovators for new work and repairs.', false, false, 'Building or construction trade certificate, or NCC registration if you have one.', [
                ['Building and house construction', 0, 5000, 500000, 'New houses and extensions, from foundations to roof. Quote after a site visit.'],
                ['Bricklaying and plastering', 0, 400, 15000, 'Walls, boundary walls, plastering and screeding.'],
                ['Tiling and flooring', 0, 400, 12000, 'Floor and wall tiles, laminate and screed.'],
                ['Roofing and ceiling installation', 250, 1000, 40000, 'New roofs, ceilings, fascia and gutters.'],
                ['House renovation and repairs', 200, 500, 60000, 'Bathrooms, kitchens, cracks, damp and general refurbishment.'],
                ['Painting and finishing', 0, 800, 20000, 'Interior and exterior painting after building work.'],
                ['Boundary walls and fencing', 0, 1500, 50000, 'Brick, block, palisade and electric fencing.'],
                ['Building plans and site supervision', 0, 800, 20000, 'Drawings, approvals and a supervisor to oversee your builder.'],
            ]],
        ];

        $order = (int) Category::max('sort_order') + 1;
        foreach ($new as [$name, $icon, $short, $emergency, $licence, $hint, $services]) {
            $cat = Category::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'icon' => $icon, 'short_description' => $short, 'description' => $short,
                'is_active' => true, 'is_emergency' => $emergency, 'license_required' => $licence, 'license_hint' => $hint, 'sort_order' => $order++,
            ]);
            $this->services($cat, $services);
        }

        // Gas refill and installation (existing category).
        if ($gas = Category::where('slug', 'gas-services')->first()) {
            $this->services($gas, [
                ['Gas refill (LPG)', 50, 100, 900, 'Refill of your own LPG cylinder at your home or business (gas charged at current price).'],
                ['Gas installation (full system)', 200, 400, 6000, 'Piping, regulators and appliance connections for kitchens, restaurants and homes, with a safety test.'],
                ['Gas cylinder and regulator supply', 50, 150, 1500, 'New cylinders, regulators and hoses supplied and fitted.'],
            ]);
        }
    }

    private function services(Category $cat, array $services): void
    {
        foreach ($services as [$sname, $callout, $min, $max, $desc]) {
            $cat->services()->firstOrCreate(['slug' => Str::slug($sname)], [
                'name' => $sname, 'description' => $desc, 'callout_fee' => $callout, 'min_price' => $min, 'max_price' => $max, 'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        // Catalogue data is left in place.
    }
};
