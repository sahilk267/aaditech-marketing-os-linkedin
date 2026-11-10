<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['slug'=>'aadi-tech-solutions','name'=>'Aadi Tech Solutions','objective'=>'B2B IT services leads',
                'topics'=>['IT infrastructure','networking','cybersecurity awareness','servers','cloud','managed IT'],
                'audience'=>['IT managers','business owners','operations managers','procurement'],
                'cta'=>'Request an IT consultation','prohibited_claims'=>['Unverified security guarantees','Fabricated case studies']],
            ['slug'=>'overseas-job-placement','name'=>'Overseas Job Placement','objective'=>'Verified job seeker and employer enquiries',
                'topics'=>['verified vacancies','career guidance','interview preparation'],
                'audience'=>['job seekers','employers'],
                'cta'=>'View a verified vacancy or submit an enquiry',
                'prohibited_claims'=>['Invented vacancies','Guaranteed placement','Unverified salaries or visas'],
                'requires_official_evidence'=>true],
            ['slug'=>'aadipro','name'=>'AadiPro','objective'=>'Verified product or service awareness and qualified enquiries',
                'topics'=>[],'audience'=>[], 'cta'=>'Submit an enquiry',
                'verified_catalog'=>[], 'prohibited_claims'=>['Unverified product or service offerings']],
            ['slug'=>'toolboxgalaxy','name'=>'ToolboxGalaxy','objective'=>'Product discovery, organic website traffic and repeat use',
                'topics'=>['online tools','productivity tutorials','developer utilities','tool demonstrations'],
                'audience'=>[],
                'cta'=>'Open the verified tool page','verified_tool_urls'=>[],
                'prohibited_claims'=>['Unverified tool capabilities']],
        ];
        foreach ($brands as $row) {
            $config = array_diff_key($row, array_flip(['slug','name']));
            Brand::firstOrCreate(['slug' => $row['slug']], [
                'name' => $row['name'], 'brand_config' => $config, 'is_active'=>true,
            ]);
        }
    }
}
