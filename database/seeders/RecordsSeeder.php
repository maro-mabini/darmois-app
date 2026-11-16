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
     * A sample of the records and files held in the cabinets, with the names
     * replaced by placeholders.
     *
     * It fills a server with the same shape as the real archive — the same
     * lookups, record counts, and files per record — without carrying anyone's
     * personal data into the repository.
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
            'owner_name' => 'SAMPLE LANDOWNER 01',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_01',
                    'file_description' => 'Original copy of title (sample record)',
                    'date_of_file' => '1995-08-18',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 02',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_02',
                    'file_description' => "Attachment:\nCARP Form No. 19 (sample)",
                    'date_of_file' => '1991-07-08',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 03',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_03',
                    'file_description' => "Attachment\nOriginal CARP Form No. 20 (sample)",
                    'date_of_file' => '1993-09-08',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 04',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_04',
                    'file_description' => "Attachment\nOriginal CARP Form No. 18 (sample)",
                    'date_of_file' => '1993-09-08',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 05',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_05',
                    'file_description' => 'CARP Form No. 19 (sample)',
                    'date_of_file' => '1993-09-07',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 06',
            'owner_type' => 'Non-ARB',
            'record_type' => 'DAR CLEARANCE',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LTC Form No. 2',
                    'file_description' => 'Sample MARPO certification',
                    'date_of_file' => '2026-05-04',
                ],
                [
                    'file_name' => 'Letter Request',
                    'file_description' => 'Sample request letter for a certification',
                    'date_of_file' => '2026-04-06',
                ],
                [
                    'file_name' => 'LTC Form 1',
                    'file_description' => 'Sample application for land transfer clearance',
                    'date_of_file' => '2026-03-11',
                ],
                [
                    'file_name' => 'Deed of Absolute Sale',
                    'file_description' => 'Sample deed of absolute sale',
                    'date_of_file' => '2026-05-08',
                ],
                [
                    'file_name' => 'Original Certificate of Title (Photocopy)',
                    'file_description' => 'Sample certificate of title, photocopy',
                    'date_of_file' => '2008-09-30',
                ],
                [
                    'file_name' => 'Tax Declaration of Real Property',
                    'file_description' => 'Sample tax declaration',
                    'date_of_file' => '2026-03-17',
                ],
                [
                    'file_name' => 'Joint-Affidavit of Aggregate Landholding',
                    'file_description' => 'Sample joint affidavit of aggregate landholding',
                    'date_of_file' => '2026-03-11',
                ],
                [
                    'file_name' => 'Affidavit of Non-Tenancy, Aggregate Landholding, Retention and No Pending Case',
                    'file_description' => 'Sample affidavit of non-tenancy',
                    'date_of_file' => '2026-03-11',
                ],
                [
                    'file_name' => 'Municipal Assessor\'s Certification',
                    'file_description' => 'Sample municipal certification, first copy',
                    'date_of_file' => '2026-03-26',
                ],
                [
                    'file_name' => 'Municipal Assessor\'s Certification',
                    'file_description' => 'Sample municipal certification, second copy',
                    'date_of_file' => '2026-03-26',
                ],
                [
                    'file_name' => 'Provincial Assessor\'s Certification',
                    'file_description' => 'Sample provincial certification, first copy',
                    'date_of_file' => '2026-03-17',
                ],
                [
                    'file_name' => 'Provincial Assessor\'s Certification',
                    'file_description' => 'Sample provincial certification, second copy',
                    'date_of_file' => '2026-03-17',
                ],
                [
                    'file_name' => 'Provincial Assessor\'s Certification',
                    'file_description' => 'Sample provincial certification, third copy',
                    'date_of_file' => '2026-04-06',
                ],
                [
                    'file_name' => 'Provincial Assessor\'s Certification',
                    'file_description' => 'Sample provincial certification, fourth copy',
                    'date_of_file' => '2026-04-07',
                ],
                [
                    'file_name' => 'Special Power of Attorney',
                    'file_description' => 'Sample special power of attorney',
                    'date_of_file' => '2026-04-11',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 07',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_07',
                    'file_description' => "Attachments\nOriginal file: CARP Form No. 20 (sample)",
                    'date_of_file' => '1993-08-30',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 08',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_08',
                    'file_description' => "Attachments:\nSample certification",
                    'date_of_file' => '1994-09-15',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 09',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_09',
                    'file_description' => 'Sample CARP forms 18 and 20',
                    'date_of_file' => '1996-12-27',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 10',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_10',
                    'file_description' => 'Sample CARP Form No. 19 (bank-held)',
                    'date_of_file' => '2003-12-15',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 11',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_11',
                    'file_description' => "CARP Form No. 19\nCARP Form No. 20\nTransfer certificate of title (photocopy)",
                    'date_of_file' => '2002-06-03',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 12',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_12',
                    'file_description' => 'Sample technical description',
                    'date_of_file' => '2003-03-12',
                ],
                [
                    'file_name' => 'LDF_SAMPLE_12',
                    'file_description' => 'Sample transfer certificate of title (photocopy)',
                    'date_of_file' => '2003-03-10',
                ],
                [
                    'file_name' => 'LDF_SAMPLE_12',
                    'file_description' => 'Sample CARP Form No. 19',
                    'date_of_file' => '2003-03-18',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 13',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_13',
                    'file_description' => "Attachments:\nCARP Form No. 19 (sample)\nTCT (photocopy)\nSample certificate of posting",
                    'date_of_file' => '1998-11-11',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 14',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_14',
                    'file_description' => "Attachments:\nSample CARP Form No. 19\nSample CARP Form No. 18",
                    'date_of_file' => '1991-09-06',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 15',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_15',
                    'file_description' => "Attachments:\nSample CARP Form No. 20\nSample CARP Form No. 18",
                    'date_of_file' => '1994-01-13',
                ],
            ],
        ],
        [
            'owner_name' => 'SAMPLE LANDOWNER 16',
            'owner_type' => 'LANDOWNER',
            'record_type' => 'LAND DISTRIBUTION FOLDER (LDF)',
            'location' => 'CABINET 1',
            'station' => 'DARMO - MABINI',
            'files' => [
                [
                    'file_name' => 'LDF_SAMPLE_16',
                    'file_description' => "Attachments:\nSample CARP Form No. 20\nSample CARP Form No. 19",
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
