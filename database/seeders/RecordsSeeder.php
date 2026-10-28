<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\OwnerType;
use App\Models\RecordsOwner;
use App\Models\RecordType;
use App\Models\Station;
use Illuminate\Database\Seeder;

class RecordsSeeder extends Seeder
{
    /**
     * The records and files held in the cabinets, copied out of the local database
     * so a server can be filled without re-importing the whole dump.
     *
     * Lookups are named rather than given as ids, because the ids they hold on one
     * database say nothing about the ids they hold on another. Each one is created
     * only when it is missing.
     *
     * @var list<array{
     *     owner_name: string,
     *     owner_type: string,
     *     record_type: string,
     *     location: string|null,
     *     station: string|null,
     *     files: list<array{file_name: string, file_description: string|null, date_of_file: string|null}>
     * }>
     */
    private const RECORDS = [
        [
            'owner_name' => 'CRESENCIANO ALBULARIO_01',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_ALBULARIO',
                    'file_description' => 'Original Copy of Title named to Cresenciano Albulario with title number T-108519',
                    'date_of_file' => '1995-08-18',
                ],
            ],
        ],
        [
            'owner_name' => 'CRESENCIANO ALBULARIO_02',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_ALBULARIO',
                    'file_description' => "Attachment:\nCARP Form No. 19 with TCT/OCT No. (T-2682) T-688",
                    'date_of_file' => '1991-07-08',
                ],
            ],
        ],
        [
            'owner_name' => 'CRESENCIANO ALBULARIO_03',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_ALBULARIO',
                    'file_description' => "Attachment\nOriginal CARP Form No. 20 with Title No. P-10008",
                    'date_of_file' => '1993-09-08',
                ],
            ],
        ],
        [
            'owner_name' => 'CRESENCIANO ALBULARIO_04',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_ALBULARIO',
                    'file_description' => "Attachment\nOriginal CARP Form No. 18 with Title No. T-777",
                    'date_of_file' => '1993-09-08',
                ],
            ],
        ],
        [
            'owner_name' => 'CRESENCIANO ALBULARIO_05',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_ALBULARIO',
                    'file_description' => 'CARP From No. 19 with Title No. P-21896',
                    'date_of_file' => '1993-09-07',
                ],
            ],
        ],
        [
            'owner_name' => 'IDA A. ENTIZE',
            'owner_type' => 'Non-ARB',
            'record_type' => 'DAR CLEARANCE',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LTC Form No. 2',
                    'file_description' => 'MARPO Certification ',
                    'date_of_file' => '2026-05-04',
                ],
                [
                    'file_name' => 'Letter Request',
                    'file_description' => 'Reynato C. Robillos\'s Request for the issuance of the MARPO Certification addressed to MARO Mabini for title No. P-49219, Lot No. 1167-D, Csd-11-017594. ',
                    'date_of_file' => '2026-04-06',
                ],
                [
                    'file_name' => 'LTC Form 1',
                    'file_description' => 'Application for Issunace of Land Transfer Clearance duly notarized by Atty. Vitaliano A. Valmoria',
                    'date_of_file' => '2026-03-11',
                ],
                [
                    'file_name' => 'Deed of Absolute Sale',
                    'file_description' => 'Deed entered into by Ida A. Entize, Ricardo A. Perez, Mark Aljon L. Perez, Mark Vincent L Perez and Ma. Maurine L. Perez. Duly notarized by Atty. Vivencio V. Jumamil',
                    'date_of_file' => '2026-05-08',
                ],
                [
                    'file_name' => 'Original Certificate of Title (Photocopy)',
                    'file_description' => 'Title No. P-49219, Lot No. 1167-D, Csd-11-017594 - portion of lot 1176 Cad-277',
                    'date_of_file' => '2008-09-30',
                ],
                [
                    'file_name' => 'Tax Declaration of Real Property',
                    'file_description' => 'TD No. 20-03-0008-00395',
                    'date_of_file' => '2026-03-17',
                ],
                [
                    'file_name' => 'Joint-Affidavit of Aggregate Landholding',
                    'file_description' => 'Vendee/Transferor\'s Affidavit duly notarized by Atty. Vitaliano A. Valmoria',
                    'date_of_file' => '2026-03-11',
                ],
                [
                    'file_name' => 'Affidavit of Non-Tenancy, Aggregate Landholding, Retention and No Pending Case',
                    'file_description' => 'Affidavit executed by the Vendor duly notarized by Atty. Vitaliano A. Vilmoria',
                    'date_of_file' => '2026-03-11',
                ],
                [
                    'file_name' => 'Municipal Assessor\'s Certification',
                    'file_description' => 'Certification issued by the Municipal Assessor\'s Office of Mabini, Davao de Oro to Ida A. Entize',
                    'date_of_file' => '2026-03-26',
                ],
                [
                    'file_name' => 'Municipal Assessor\'s Certification',
                    'file_description' => 'Certification issued by the Municipal Assessro\'s Office of Mabini, Davao de Oro to Ricardo A. Perez, Mark Aljon L. Perez, Ma. Maurine L. Perez and Mark Vincent L. Perez',
                    'date_of_file' => '2026-03-26',
                ],
                [
                    'file_name' => 'Provincial Assessor\'s Certification',
                    'file_description' => 'Certification as to Aggregate Landholding issued by the Provincial Assossor\'s Office of Davao de Oro to Ida A. Entize',
                    'date_of_file' => '2026-03-17',
                ],
                [
                    'file_name' => 'Provincial Assessor\'s Certification',
                    'file_description' => 'Certification as to aggregate landholding issued by the Provincial Asseessor\'s Office of Davao de Oro to Ricardo A. Perez, Mark Aljon L. Perez, Ma. Maurine L. Perez and Mark Vincent L. Perez.',
                    'date_of_file' => '2026-03-17',
                ],
                [
                    'file_name' => 'Provincial Assessor\'s Certification',
                    'file_description' => 'Certification as to aggregate landholding issued by the Provincial Assossor\'s Office of Davao del Norte to Ida A. Entize',
                    'date_of_file' => '2026-04-06',
                ],
                [
                    'file_name' => 'Provincial Assessor\'s Certification',
                    'file_description' => 'Certification as to aggregate landholding issued by the Provincial Asseessor\'s Office of Davao del Norte to Ricardo A. Perez, Mark Aljon L. Perez, Ma. Maurine L. Perez and Mark Vincent L. Perez.',
                    'date_of_file' => '2026-04-07',
                ],
                [
                    'file_name' => 'Special Power of Attorney',
                    'file_description' => 'Executed by the vendees and Runel L. Robillos',
                    'date_of_file' => '2026-04-11',
                ],
            ],
        ],
        [
            'owner_name' => 'ESPERANZA ALBULARIO _06',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_ALBULARIO',
                    'file_description' => "Attachments\nOriginal File: CARP Form No. 20\nCertification FB Applicants",
                    'date_of_file' => '1993-08-30',
                ],
            ],
        ],
        [
            'owner_name' => 'BANCO DAVAO (C. DEJACTO)_07',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_BANCO DAVAO (C. DEJACTO)',
                    'file_description' => "Attachments:\nCERTIFICATION\nFB Applicants of the landholding registered in the name of Banco Davao (Columbus Dejacto) covered by title no. T-17349",
                    'date_of_file' => '1994-09-15',
                ],
            ],
        ],
        [
            'owner_name' => 'DELFIN CENIZA_08',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_CENIZA',
                    'file_description' => 'CARPER LAD Forms  18 & 20 of Delfin Ceniza with Title No. T-50032',
                    'date_of_file' => '1996-12-27',
                ],
            ],
        ],
        [
            'owner_name' => 'DBP (L. ARADANAS)_09',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_DBP (L. ARADANAS)',
                    'file_description' => 'CARP Form No. 19 of DBP (L. Aradanas) with Title No. T-11336',
                    'date_of_file' => '2003-12-15',
                ],
            ],
        ],
        [
            'owner_name' => 'RAFAEL T. ESTIPONA_10',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_ESTIPONA',
                    'file_description' => "CARP Form No. 19\nCARP Form No. 20\nCARP Form 4\nTransfer Certificate of Title (Photocopy): T-174933",
                    'date_of_file' => '2002-06-03',
                ],
            ],
        ],
        [
            'owner_name' => 'JUANITO GAZMEN_11',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_GAZMEN: ',
                    'file_description' => '1 copy of original technical description',
                    'date_of_file' => '2003-03-12',
                ],
                [
                    'file_name' => 'LDF_GAZMEN',
                    'file_description' => "Transfer Certificate Title No. T-182783 (photocopy)\n",
                    'date_of_file' => '2003-03-10',
                ],
                [
                    'file_name' => 'LDF_GAZMEN',
                    'file_description' => "CARP Form No. 19\n",
                    'date_of_file' => '2003-03-18',
                ],
            ],
        ],
        [
            'owner_name' => 'FRITZ_GEMPERLE_12',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_GEMPERLE (T-34222)',
                    'file_description' => "Attachments:\nCARP Form No. 19\nTCT (Photocopy)\nCertificate of Posting\n",
                    'date_of_file' => '1998-11-11',
                ],
            ],
        ],
        [
            'owner_name' => 'SANTOS JUMAWAN_13',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_JUMAWAN (P-7978)',
                    'file_description' => "Attachments:\nCARP Form No. 19 (Original)\nCARP From No. 18 (Original)\n",
                    'date_of_file' => '1991-09-06',
                ],
            ],
        ],
        [
            'owner_name' => 'HEIRS OF MANDALANO MANSACA_14',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_HRS MANSACA (P-11094)',
                    'file_description' => "Attachments:\nCARP Form No. 20\nCARP Form No. 18\nCARP Form No. 19\n",
                    'date_of_file' => '1994-01-13',
                ],
            ],
        ],
        [
            'owner_name' => 'FERNANDO MONTELIBANO_15',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_MONTELIBANO (T-8194)',
                    'file_description' => "Attachments:\nCARP Form No. 20\nCARP Form No. 19\nCARP Form No. 18",
                    'date_of_file' => '1990-02-04',
                ],
            ],
        ],
    ];

    /**
     * Seed the records, their files, and the lookups they depend on.
     *
     * Safe to run more than once: a record is matched on its owner and record type,
     * and a file on its record, name and description, so a second run adds nothing
     * and leaves whatever already sits in the table alone.
     */
    public function run(): void
    {
        foreach (self::RECORDS as $record) {
            $recordType = RecordType::query()->firstOrCreate([
                'name' => $record['record_type'],
            ]);

            $ownerType = OwnerType::query()->firstOrCreate([
                'name' => $record['owner_type'],
            ]);

            $locationId = $this->lookupId(Location::class, $record['location']);
            $stationId = $this->lookupId(Station::class, $record['station']);

            $owner = RecordsOwner::query()->firstOrCreate(
                [
                    'owner_name' => $record['owner_name'],
                    'record_type_id' => $recordType->id,
                ],
                [
                    'owner_type_id' => $ownerType->id,
                    'location_id' => $locationId,
                    'station_id' => $stationId,
                ],
            );

            foreach ($record['files'] as $file) {
                $owner->files()->firstOrCreate(
                    [
                        'file_name' => $file['file_name'],
                        'file_description' => $file['file_description'],
                    ],
                    [
                        'date_of_file' => $file['date_of_file'],
                    ],
                );
            }
        }
    }

    /**
     * The id of the named location or station, or null when the record sits in neither.
     *
     * @param  class-string<Location|Station>  $lookup
     */
    private function lookupId(string $lookup, ?string $name): ?int
    {
        return $name === null
            ? null
            : (int) $lookup::query()->firstOrCreate(['name' => $name])->getKey();
    }
}
