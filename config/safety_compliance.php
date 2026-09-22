<?php

declare(strict_types=1);

/*
| Legal appointments and safety file contents for construction sites, based on the Occupational Health
| and Safety Act 85 of 1993 and the Construction Regulations, 2014. References are for guidance:
| confirm the list with the client's registered H&S practitioner (docs/assumptions.md HS4-HS5).
|
| 'required' => true: the project's appointment register shows a gap until someone is appointed.
| 'competency' => true: the appointee needs a competency certificate with an expiry date.
*/

return [
    'appointments' => [
        'principal_contractor' => ['label' => 'Principal contractor', 'reference' => 'Construction Regs 5', 'required' => true, 'competency' => false],
        'construction_manager' => ['label' => 'Construction manager', 'reference' => 'Construction Regs 8(1)', 'required' => true, 'competency' => true],
        'construction_supervisor' => ['label' => 'Construction supervisor', 'reference' => 'Construction Regs 8(7)', 'required' => true, 'competency' => true],
        'safety_officer' => ['label' => 'Construction health and safety officer', 'reference' => 'Construction Regs 8(5)', 'required' => true, 'competency' => true],
        'fall_protection_planner' => ['label' => 'Fall protection plan developer', 'reference' => 'Construction Regs 10', 'required' => false, 'competency' => true],
        'scaffold_inspector' => ['label' => 'Scaffolding inspector', 'reference' => 'Construction Regs 16', 'required' => false, 'competency' => true],
        'excavation_supervisor' => ['label' => 'Excavation supervisor', 'reference' => 'Construction Regs 13', 'required' => false, 'competency' => true],
        'temporary_works_designer' => ['label' => 'Temporary works designer', 'reference' => 'Construction Regs 12', 'required' => false, 'competency' => true],
        'mobile_plant_supervisor' => ['label' => 'Construction vehicles and mobile plant supervisor', 'reference' => 'Construction Regs 23', 'required' => false, 'competency' => true],
        'hs_representative' => ['label' => 'Health and safety representative', 'reference' => 'OHS Act s17', 'required' => true, 'competency' => true],
        'first_aider' => ['label' => 'First aider', 'reference' => 'General Safety Regs 3', 'required' => true, 'competency' => true],
        'fire_marshal' => ['label' => 'Fire marshal / emergency coordinator', 'reference' => 'Emergency plan', 'required' => false, 'competency' => true],
    ],

    'safety_file' => [
        'hs_specification' => 'Client health and safety specification',
        'hs_plan' => 'Contractor health and safety plan (approved)',
        'construction_permit' => 'Construction work permit or notification of construction work',
        'coida' => 'COIDA letter of good standing',
        'risk_assessments' => 'Baseline and task risk assessments',
        'method_statements' => 'Safe work method statements',
        'fall_protection_plan' => 'Fall protection plan',
        'appointments' => 'Signed legal appointments (see register)',
        'medical_fitness' => 'Medical certificates of fitness',
        'induction' => 'Site induction records',
        'toolbox_talks' => 'Toolbox talk register',
        'ppe_register' => 'PPE issue register',
        'inspection_registers' => 'Inspection registers (scaffolds, ladders, electrical, plant)',
        'incident_register' => 'Incident register and investigations',
        'emergency_plan' => 'Emergency and evacuation plan',
        'hazardous_substances' => 'Hazardous substances register and safety data sheets',
    ],
];
