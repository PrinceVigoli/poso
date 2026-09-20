<?php

/*
|--------------------------------------------------------------------------
| Municipal ordinances published on the landing page
|--------------------------------------------------------------------------
|
| Every title, provision and peso figure below was read directly off the
| signed ordinance page rather than taken from OCR, because six of these
| documents are scans with no text layer at all and the OCR on the rest
| mangles exactly the characters that matter in a fine amount.
|
| This is a transcription for public guidance. The signed ordinance on file
| with the Sangguniang Bayan remains the authority, and the office should
| proofread this file against the originals before relying on it.
|
| Source scans: public/ordinances/ordinance-<number>-<year>.pdf
|
| Per entry:
|   number, year, theme  - identity and grouping
|   title                - the SHORT TITLE / TITLE section, verbatim
|   summary              - one line of plain-language orientation
|   provisions           - what the ordinance requires or forbids
|   penalties            - ['offense' => ..., 'penalty' => ...]
|   note                 - optional caveat shown under the penalties
|
*/

$ladder = 'First ₱150 · Second ₱300 · Third ₱500 · Fourth 1 month suspension · Fifth cancellation of MTOP';

return [
    'themes' => [
        'traffic' => 'Traffic & road use',
        'transport' => 'Tricycles & public transport',
        'order' => 'Public order & safety',
    ],

    'ordinances' => [
        [
            'number' => '587', 'year' => '2025', 'theme' => 'traffic',
            'title' => 'Regulation of Light Electric Vehicles Ordinance of the Municipality of Luna, Apayao',
            'summary' => 'Covers e-bikes, e-scooters and other light electric vehicles weighing 50 kg or less.',
            'provisions' => [
                'Light electric vehicles used on public highways must be registered with the LTO, and the driver must hold a valid driver\'s licence.',
                'Light electric vehicles used only on private roads are not required to be registered.',
                'Riders of all two-wheeled electric vehicles must wear a protective helmet, as required for motorcycle riders under the Helmet Law (RA 10054).',
                'The Municipal Mayor\'s Office identifies designated e-bike routes; e-bike parking must be provided in commercial areas, public spaces and transport hubs.',
            ],
            'penalties' => [
                ['offense' => 'Operating an unregistered electric vehicle on a public highway', 'penalty' => 'Impoundment of the vehicle'],
            ],
            'note' => 'This ordinance sets no schedule of fines. Impoundment is the stated remedy.',
        ],
        [
            'number' => '582', 'year' => '2025', 'theme' => 'traffic',
            'title' => 'Child Safety in Motor Vehicles Ordinance of the Municipality of Luna, Apayao',
            'summary' => 'Child restraint rules for motor vehicles. Tricycles and motorcycles are excluded.',
            'provisions' => [
                'Children twelve (12) years old and below must be in a child restraint system while on board a closed motor vehicle with the engine running, unless the child is at least 150 cm (59 inches) tall.',
                'Children below 150 cm and aged 12 or below may not sit in the front passenger seat of a running motor vehicle.',
                'A child may never be left in a motor vehicle unaccompanied by an adult, even when a child restraint system is used.',
                'Manufacturing, using, selling, distributing or advertising substandard or expired child restraint systems is unlawful.',
                'Exemptions apply in medical emergencies, or where a medical, developmental or psychological condition means a child seat would do more harm than good.',
            ],
            'penalties' => [
                ['offense' => 'First offense', 'penalty' => '₱1,000.00'],
                ['offense' => 'Second offense', 'penalty' => '₱2,000.00'],
                ['offense' => 'Third offense', 'penalty' => '₱2,500.00'],
            ],
            'note' => 'Using a substandard child seat carries the same fines. Enforced by the Luna Municipal Police Station and the Municipal Public Order and Safety Office.',
        ],
        [
            'number' => '577', 'year' => '2025', 'theme' => 'order',
            'title' => 'Road Clearing Ordinance of the Municipality of Luna, Apayao',
            'summary' => 'Road clearing operations that reclaim roads, sidewalks and alleys from obstruction.',
            'provisions' => [
                'Illegally parked vehicles, e-bikes and e-trikes are subject to clearing — including parking within an intersection, on a crosswalk, within 6 m of a curb line intersection, within 4 m of a fire hydrant or a fire or police station driveway, in front of a private driveway, or on a bridge or within 30 m of its approach.',
                'Double parking, parking diagonally on the roadway, and leaving stalled vehicles unattended all count as obstruction.',
                'Vending sites, store and house encroachments, protruding gates, indiscriminate signage, and sports activities on the road are prohibited obstructions.',
                'Drying rice or crops on the road, and leaving construction materials, debris, junk or makeshift shelters on it, are also prohibited.',
                'Displaced vendors are given priority for stalls in the Municipal Public Market, with financial and technical assistance for livelihood development.',
            ],
            'penalties' => [],
            'note' => 'Penalties are applied through the road clearing task team and the related obstruction ordinances. Complaints go to the Road Clearing Assistance Desk Officer or the LGU Facebook page.',
        ],
        [
            'number' => '575', 'year' => '2025', 'theme' => 'order',
            'title' => 'Municipal Public Order and Safety Office Ordinance of Luna, Apayao',
            'summary' => 'The ordinance that created POSO. It establishes this office and defines what it does.',
            'provisions' => [
                'The Municipal Public Order and Safety Office is established under the Office of the Municipal Mayor.',
                'POSO conducts public information and awareness drives on criminality and public safety services.',
                'POSO assists in the capability build-up of the Barangay Police Force and civil society organisations to maintain peace and order.',
                'POSO assists during disaster or calamity and helps restore essential public utilities.',
                'POSO augments auxiliary services and other agencies related to traffic management, and enforces local ordinances — especially public order and safety laws.',
            ],
            'penalties' => [],
            'note' => 'This ordinance creates an office. It imposes no fines on the public.',
        ],
        [
            'number' => '541', 'year' => '2024', 'theme' => 'transport',
            'title' => 'Anti-Colorum Ordinance of the Municipality of Luna, Apayao',
            'summary' => 'Targets colorum tricycles — public transport operating without a valid franchise or permit.',
            'provisions' => [
                'Colorum tricycles may not operate or convey passengers in the municipality. If apprehended, the tricycle is impounded and released only on payment of the fine and impounding fee.',
                'A driver or operator caught operating a colorum tricycle is permanently disqualified from applying for a franchise; an existing franchise is immediately revoked.',
                'A Traffic Citation Ticket is issued and the LGU-issued Driver\'s ID is confiscated. The ticket is effective for ten (10) calendar days.',
                'The fine is paid at the Municipal Treasurer\'s Office on weekdays within ten (10) calendar days from the date of apprehension.',
                'Three consecutive violations within six (6) months, or failure or refusal to pay any fine, causes immediate cancellation of the franchise.',
            ],
            'penalties' => [
                ['offense' => 'Colorum operation', 'penalty' => '₱2,500.00 and impoundment of the tricycle, plus ₱50.00 per day impounding fee'],
                ['offense' => 'Unauthorized body number', 'penalty' => 'First ₱500.00 and impoundment (₱50.00/day impounding fee) · Second cancellation of franchise'],
                ['offense' => 'Allowing an unlicensed person to drive', 'penalty' => 'First ₱300.00 · Second ₱500.00 · Third ₱1,000.00'],
            ],
        ],
        [
            'number' => '495', 'year' => '2023', 'theme' => 'transport',
            'title' => 'The 2023 Tricycle Fare Rates Ordinance of the Municipality of Luna, Apayao',
            'summary' => 'Sets tricycle fare rates and the penalties for overcharging.',
            'provisions' => [
                'Prescribes the tricycle fare rates for the municipality, repealing the earlier fare ordinance.',
                'The fare matrix (taripa) must be posted where passengers can see it.',
                'Students, senior citizens and persons with disability are entitled to a fare discount.',
            ],
            'penalties' => [
                ['offense' => 'Overcharging of fares', 'penalty' => '₱2,000.00 and/or suspension of MTOP'],
                ['offense' => 'Non-posting of the fare matrix (taripa)', 'penalty' => '₱1,000.00'],
                ['offense' => 'Not giving the discount to students, senior citizens or PWDs', 'penalty' => '₱500.00'],
            ],
            'note' => 'Imposed after due investigation finds a passenger complaint to be true and valid.',
        ],
        [
            'number' => '473', 'year' => '2023', 'theme' => 'traffic',
            'title' => "Luna's Localization and Implementation of Children's Safety on Motorcycles",
            'summary' => 'When a child may ride on a motorcycle, and when they may not.',
            'provisions' => [
                'It is unlawful to drive a two-wheeled motorcycle with a child on board on public roads with a heavy volume of vehicles, a high density of fast-moving vehicles, or a speed limit above 60 km/h.',
                'A child may ride on those roads only if all three conditions are met: the child can comfortably reach the standard foot peg; the child\'s arms can reach around and grasp the rider\'s waist; and the child wears a standard protective helmet under RA 11054.',
                'A "child" here means any person below eighteen (18) years of age.',
                'The ordinance does not apply where the child being transported requires immediate medical attention.',
            ],
            'penalties' => [
                ['offense' => 'First offense', 'penalty' => '₱1,000.00'],
                ['offense' => 'Second offense', 'penalty' => '₱2,000.00'],
                ['offense' => 'Third and succeeding offenses', 'penalty' => '₱2,500.00'],
            ],
        ],
        [
            'number' => '464', 'year' => '2022', 'theme' => 'traffic',
            'title' => 'Anti-Minors on Motorized Wheels Ordinance of the Municipality of Luna, Apayao',
            'summary' => 'Minors driving motor vehicles, and the adults who let them.',
            'provisions' => [
                'A minor may not drive any motor vehicle alone within the municipality.',
                'The one exception: a 17-year-old holding an LTO student permit, accompanied by a parent or guardian who holds a valid Professional or Non-Professional driver\'s licence.',
                'It is unlawful for any person of legal age to allow, lend, consent to, direct or induce a minor to drive a motor vehicle for any purpose.',
                'The registered owner is presumed to have allowed it — registration establishes a prima facie case against the owner.',
                'Minor offenders undergo mandatory guidance counselling with the MSWD Officer in the presence of a parent or guardian.',
            ],
            'penalties' => [
                ['offense' => 'Any person found guilty', 'penalty' => 'Imprisonment of not less than three (3) months and not more than six (6) months, or a fine of ₱2,500.00, or both, as the Court may impose'],
            ],
            'note' => 'Under Article 2180 of the Civil Code, parents are civilly liable for damage caused by their unemancipated children living in their company.',
        ],
        [
            'number' => '415', 'year' => '2020', 'theme' => 'transport',
            'title' => 'Tricycle Franchising Ordinance',
            'summary' => 'Franchising and operation of tricycles for hire, including the MTOP and operating zones.',
            'provisions' => [
                'Regulates and grants franchises for tricycles-for-hire operating within the municipality.',
                'A Motorized Tricycle Operator\'s Permit (MTOP) is the licence that authorises operation for public transport.',
                'Only one classification of tricycle-for-hire is allowed: a motorcycle with an attached sidecar supported by one wheel.',
                'An impounding area is established under Ordinance No. 403 for immobilised and towed vehicles.',
            ],
            'penalties' => [
                ['offense' => 'Operating without, or with an expired, franchise', 'penalty' => '₱2,500.00 and impoundment of the tricycle'],
                ['offense' => 'Driving under the influence of alcohol', 'penalty' => 'Cancellation of MTOP, plus the penalties under the Anti-Drunk and Drugged Driving Act of 2013'],
                ['offense' => 'Smoking while operating', 'penalty' => 'Cancellation of MTOP, plus the penalty under the Tobacco Regulation Act of 2003'],
                ['offense' => 'Maltreatment or disrespect of passengers', 'penalty' => '₱2,000.00 and cancellation of MTOP'],
                ['offense' => 'Overcharging of fares', 'penalty' => '₱2,000.00, suspension of MTOP and impoundment of the tricycle'],
                ['offense' => 'Overloading', 'penalty' => '₱2,500.00 and suspension of MTOP'],
                ['offense' => 'Not posting the fare matrix, ID card or MTOP certificate', 'penalty' => '₱1,000.00 and warning for suspension of franchise'],
                ['offense' => 'Loud sound system, removed silencer, modified muffler, or missing body number, stickers or decals', 'penalty' => '₱2,500.00 and warning for suspension of franchise'],
                ['offense' => 'Refusal to convey passengers', 'penalty' => '₱500.00 and warning for suspension of franchise'],
            ],
        ],
        [
            'number' => '403', 'year' => '2020', 'theme' => 'traffic',
            'title' => 'Prescribing Guidelines on Towing and Impounding of Illegally Parked and Stalled Vehicles Within the Municipality of Luna, Apayao',
            'summary' => 'What it costs to get a towed or impounded vehicle back.',
            'provisions' => [
                'Establishes the municipal impounding area and the guidelines for towing and impounding illegally parked or stalled vehicles.',
                'Fines and towing fees are paid within five (5) days from the date the vehicle was towed or impounded.',
                'A vehicle is released only after the fines and the towing and impounding fee are paid at the Office of the Municipal Treasurer.',
                'After six (6) months without payment, the vehicle may be treated as abandoned and sold at public auction under Commission on Audit rules.',
            ],
            'penalties' => [
                ['offense' => 'Towing fee', 'penalty' => '₱500.00 for the first 4 km, plus ₱50.00 for each succeeding km to the impounding area'],
                ['offense' => 'Impounding fee — first and second month', 'penalty' => '₱100.00 per day'],
                ['offense' => 'Impounding fee — third and fourth month', 'penalty' => '₱200.00 per day'],
                ['offense' => 'Impounding fee — fifth and sixth month', 'penalty' => '₱300.00 per day'],
            ],
            'note' => 'Higher towing rates apply to larger vehicles. Check the signed ordinance for the full fee table.',
        ],
        [
            'number' => '402', 'year' => '2020', 'theme' => 'order',
            'title' => 'Prescribing the Use and Issuance of a Citation Ticket When a Violation of Any Existing Municipal Ordinance is Committed and Providing Funds Thereof',
            'summary' => 'How a citation ticket works, and what happens if you do not settle it.',
            'provisions' => [
                'Applies to general municipal ordinances that prescribe fines. Traffic violations stay under Ordinance No. 313 as amended by No. 320.',
                'A Municipal Enforcer may be a member of the PNP, a LUKAG member, or a deputized Barangay Official, Tanod or BPAT.',
                'The ticket is issued in three copies: the original to the offender, the second to the Municipal Treasurer\'s Office, the third kept by the enforcer.',
                'The violator is advised to pay the fine to the Municipal Treasurer within five (5) days of receiving the citation ticket.',
                'If the violator does not appear or pay within 5 days, the Chief of Police issues a summons, then a second notice after 5 days, then a final notice after another 5 days.',
                'On non-compliance with the final notice, the Chief of Police coordinates with the Municipal Trial Court for the filing of a case.',
            ],
            'penalties' => [],
            'note' => 'This ordinance sets the procedure. The fine itself comes from whichever ordinance was violated.',
        ],
        [
            'number' => '387', 'year' => '2019', 'theme' => 'order',
            'title' => 'Anti Road and Sidewalk Obstruction Ordinance of the Municipality of Luna, Apayao',
            'summary' => 'Keeping public roads and sidewalks clear, and what happens when they are not.',
            'provisions' => [
                'No structure, permanent or movable, and no other obstruction may be introduced on any portion of a public road or sidewalk in Luna.',
                'Public roads may not be used for private ends — open garages, parking areas, or tents for family gatherings — except with express permission from the Municipal Government and the Barangay, for a stated time and duration.',
                'Covers national, provincial, municipal, barangay and farm-to-market roads, and sidewalks.',
                'Permitted vendors may occupy portions of public roads during municipality-sanctioned events with the necessary permit.',
                'Brief parking to shop or dine is allowed where it does not restrain the free flow of traffic, as is road occupancy during emergencies to save lives or livelihood.',
            ],
            'penalties' => [
                ['offense' => 'First offense', 'penalty' => 'Stern warning'],
                ['offense' => 'Second offense', 'penalty' => '₱1,500.00'],
                ['offense' => 'Third offense', 'penalty' => '₱2,500.00, plus confiscation of the movable obstruction or removal or demolition of the immovable structure'],
            ],
            'note' => 'Where the violator is a minor, the fine is paid by the parents or guardians. Vehicles whose owners refuse to settle may be towed, and the owner also pays the towing fee.',
        ],
        [
            'number' => '362', 'year' => '2018', 'theme' => 'order',
            'title' => 'Anti–Momma Ordinance of the Municipality of Luna, Apayao',
            'summary' => 'Where momma (betel nut) juice may not be spat, and the penalties for doing so.',
            'provisions' => [
                'Spitting or spilling momma juice is prohibited in: government offices and buildings, barangay halls, streets, waiting sheds, schools, parks, tourist spots, restaurants and other food establishments, and other public areas including public parking areas and public restrooms.',
                'Spitting momma anywhere other than into the spitter\'s own private receptacle, carried on their person, is prohibited.',
                'Spilling momma or disposing of momma material or a momma receptacle in a way that causes an unsanitary sight is prohibited.',
                'The ordinance respects betel nut chewing as an Iyapayao tradition; it regulates where the juice ends up, not the chewing itself.',
                'Ignorance of the ordinance is not a valid ground to escape the penalty.',
            ],
            'penalties' => [
                ['offense' => 'First offense', 'penalty' => '₱250.00 or 8 hours community service'],
                ['offense' => 'Second offense', 'penalty' => '₱500.00 or 16 hours community service'],
                ['offense' => 'Third offense', 'penalty' => '₱1,000.00 or 40 hours community service'],
                ['offense' => 'Refusal to comply with the penalty', 'penalty' => 'Subject to the filing of a case for violating the ordinance'],
            ],
        ],
        [
            'number' => '347', 'year' => '2017', 'theme' => 'traffic',
            'title' => 'An Ordinance to Proscribe the Use of Noisy Mufflers on Motor Vehicles Roaming Within the Municipality',
            'summary' => 'Modified and noisy mufflers are treated as a public nuisance.',
            'provisions' => [
                'No motor vehicle — car, motorcycle, motorbike, scooter or tricycle, whether private or for hire — may use a modified muffler or any device that increases the noise of the vehicle.',
                'Using noisy mufflers to roam within the jurisdiction of Luna is prohibited in order to avoid noise pollution.',
                'Anyone caught using a motor vehicle with a noisy muffler is held liable under the ordinance.',
                'The Municipal Police is the lead agency; barangay tanods of the 22 barangays and 1 administrative barangay may also apprehend violators.',
            ],
            'penalties' => [
                ['offense' => 'First offense', 'penalty' => 'Confiscation of the noisy muffler plus ₱300.00 fine'],
                ['offense' => 'Second offense', 'penalty' => 'Confiscation of the noisy muffler plus ₱500.00 fine'],
                ['offense' => 'Third offense', 'penalty' => '₱1,000.00 fine and impoundment of the motor vehicle'],
            ],
        ],
        [
            'number' => '320', 'year' => '2015', 'theme' => 'traffic',
            'title' => 'An Ordinance Adopting an Amendment to Municipal Ordinance No. 313 (Citation Ticket in the Implementation of Traffic Ordinances and Imposing Penalties Therefor)',
            'summary' => 'The traffic fine schedule. This is the table most citation tickets are written against.',
            'provisions' => [
                'Adds the penal clause to the traffic citation ticket ordinance, stating the specific fine for each violation.',
                'Fines are collected by the Municipal Treasurer or a duly authorised representative.',
                'Once the required fine is paid, liability arising from the violation of traffic and related municipal ordinances is deemed extinguished.',
            ],
            'penalties' => [
                ['offense' => 'No protective head gear', 'penalty' => '₱500.00 one-time penalty'],
                ['offense' => 'Over-speeding', 'penalty' => '₱500.00 one-time penalty'],
                ['offense' => 'Over-charging', 'penalty' => $ladder],
                ['offense' => 'Out of route', 'penalty' => $ladder],
                ['offense' => 'No rear view mirror (tricycle for hire)', 'penalty' => $ladder],
                ['offense' => 'No or defective head light, stop light or brake light', 'penalty' => $ladder],
                ['offense' => 'No fare table, or fare table not prominently posted inside the vehicle', 'penalty' => $ladder],
                ['offense' => 'Invalid or expired annual franchise permit', 'penalty' => $ladder],
                ['offense' => 'Ferrying passengers beyond the designed seating', 'penalty' => $ladder],
                ['offense' => 'Inappropriate dress — slippers, short pants, sando', 'penalty' => $ladder],
                ['offense' => 'Invalid or expired MTOP', 'penalty' => '₱500.00 and impoundment of the vehicle until compliance'],
                ['offense' => 'Operating without an MTOP', 'penalty' => '₱500.00 and impoundment of the vehicle until compliance'],
                ['offense' => 'No "Red Eye Registration", no municipal franchise plate, or no body number', 'penalty' => 'Impoundment of the vehicle until compliance with an order'],
                ['offense' => 'No garbage receptacle inside the vehicle', 'penalty' => 'First ₱500 · Second ₱1,000 and suspension of operation until compliance · Third ₱1,500 and imprisonment at the discretion of the court'],
                ['offense' => 'Operating without, or with an expired, Mayor\'s Permit', 'penalty' => 'Not less than ₱500 and not more than ₱2,500, or imprisonment at the discretion of the court'],
                ['offense' => 'Anti-littering', 'penalty' => 'Not less than ₱200 and not more than ₱500, or imprisonment at the discretion of the court'],
            ],
        ],
    ],
];
