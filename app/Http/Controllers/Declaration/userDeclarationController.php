<?php

namespace App\Http\Controllers\Declaration;

use App\Http\Controllers\Controller;
use App\Models\Asset_declaration_window;
use App\Models\Declaration_download;
use App\Models\Declaration_section;
use App\Models\Declaration_type;
use App\Models\Financial_year;
use App\Models\Section;
use App\Models\Section_requirement;
use App\Models\User;
use App\Models\User_declaration;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use stdClass;

class userDeclarationController extends Controller
{
    public function declarations(): JsonResponse
    {

        $declaration_window = Asset_declaration_window::with([
            'declarations' => function ($query) {
                $query->select('id', 'secure_token', 'type');
            }
        ])
            ->where('is_active', '=', true)
            ->select('id', 'declaration_type_id', 'is_active')
            ->get();

//        $declarations = Declaration_type::get();

        $response = ['statusCode' => 200, 'declaration_window' => $declaration_window];

        return response()->json($response, 200);

    }

    public function declarationForm($secure_token): JsonResponse
    {

        $today = Carbon::now();

        $year = Financial_year::where('is_active', '=', true)->first();

        $declaration = Declaration_type::with([
            'sections'
        ])
            ->where('secure_token', '=', $secure_token)
            ->first();

        $check = User_declaration::where('user_id', '=', auth()->user()->id)
            ->where('financial_year_id', '=', $year->id)
            ->where('declaration_type_id', '=', $declaration->id)
            ->first();

        if ($check != null) {
            if ($check->is_confirmed && $declaration->declaration_code == "TRM") {

                $response = ['statusCode' => 400, 'message' => 'Tayari umeshathibitisha kutuma tamko hili, kwahyo uwezi kujaza tena', 'data' => $check];

                return response()->json($response);
            } elseif ($check->is_confirmed && $declaration->declaration_code != "TRM") {

                $initDay = Carbon::parse($check->created_at);

                $diffDays = $initDay->diffInDays($today);

                if ($diffDays <= 7) {

                    $response = ['statusCode' => 400, 'message' => 'Uwezi kujaza tamko ili kulingana na mda uliotumia awali kujaza aina hii ya tamko,tafadhali subiri zipite siku 7 ndo uweze kujaza tena', 'data' => $check];

                    return response()->json($response);
                }

            }
        }

        $response = ['statusCode' => 200,'declaration' => $declaration];

        return response()->json($response, 200);
    }

    public function sectionRequirementsForm($secure_token): JsonResponse
    {

        $section = Section::with([
            'requirements' => function($query){
               $query->with([
                   'requirement' => function($qry){
                      $qry->select('id','label','field_name','field_type','end_point');
                   }
               ])
                   ->select('id','secure_token','section_id','requirement_id');
            }
        ])
            ->where('secure_token','=',$secure_token)
            ->first();

        $response = ['statusCode' => 200, 'section' => $section];

        return response()->json($response, 200);
    }

    public function deleteDeclaration(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'declaration_type' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $declaration = Declaration_type::find($request->input('declaration_type'));

        $year = Financial_year::where('is_active', '=', true)->first();

        $data = User_declaration::where('user_id', '=', auth()->user()->id)
            ->where('financial_year_id', '=', $year->id)
            ->where('declaration_type_id', '=', $declaration->id)
            ->delete('cascade');

        $response = ['statusCode' => 200, 'message' => 'Umefanikiwa kufuta '.$declaration->type.' kikamilifu'];

        return response()->json($response, 200);
    }

    public function declarationSave(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'declaration_type' => 'required|integer',
            'sections' => 'required|array',
            'flag' => 'required|string',
        ]);


        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $today = Carbon::now();

        try {
            $declaration = Declaration_type::find($request->input('declaration_type'));

            $year = Financial_year::where('is_active', '=', true)->first();

            $check = User_declaration::where('user_id', '=', auth()->user()->id)
                ->where('financial_year_id', '=', $year->id)
                ->where('declaration_type_id', '=', $declaration->id)
                ->first();

            $sections = $request->input('sections');

            if ($check != null) {

                if ($check->is_confirmed && $declaration->declaration_code == "TRM"){

                    $response = ['statusCode' => 400, 'message' => 'Tayari umeshathibitisha kutuma tamko hili, kwahyo uwezi kujaza tena', 'data' => $check];

                    return response()->json($response);
                }
                elseif ($check->is_confirmed && $declaration->declaration_code != "TRM"){

                    $initDay = Carbon::parse($check->created_at);

                    $diffDays = $initDay->diffInDays($today);

//                    return ['$diffDays'=> $diffDays,'$initDay' => $initDay,'today' => $today];
                    if ($diffDays <= 7){

                        $response = ['statusCode' => 400, 'message' => 'Uwezi kujaza tamko ili kulingana na mda uliotumia awali kujaza aina hii ya tamko,tafadhali subiri zipite siku 7 ndo uweze kujaza tena', 'data' => $check];

                        return response()->json($response);
                    }
                    else{

                        return $this->insertSections($sections, $check);
                    }
                }

                return $this->insertSections($sections, $check);

            }

        $user_declaration = User_declaration::create([
            'secure_token' => Str::uuid(),
            'user_id' => auth()->user()->id,
            'declaration_type_id' => $declaration->id,
            'adf_number' => $this->generateAdfNumber($declaration->declaration_code, $year->year),
            'financial_year_id' => $year->id,
            'flag' => $request->input('flag')
        ]);

        return $this->insertSections($sections, $user_declaration);

        } catch (Exception $error) {
            return response()->json([
                'statusCode' => 402,
                'message' => 'Something went wrong.',
                'error' => $error,
            ]);
        }


    }

    public function updateSection(Request $request,$id){

//       return $request->getContent();

        $table = strtolower($request['section']['table']);
        $data = $request['section']['data'];

        if (count($data) > 0) {

            foreach ($data as $values) {

                foreach ($values as $key => $value) {

                    DB::table($table)->where('id','=',$id)->update([
                        $key => $value
                    ]);
                }

                $data = DB::table($table)->orderBy('id','DESC')->first();

                $response = ['statusCode' => 200, 'message' => 'Umefanikiwa kurekebisha taarifa zako', 'table' => $table,'data' => $data];

                return response()->json($response);
            }

        }

    }

    public function declarationSubmission(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'declaration_type' => 'required|integer',
            'flag' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $declaration = Declaration_type::find($request->input('declaration_type'));

        $year = Financial_year::where('is_active', '=', true)->first();

        $data = User_declaration::where('user_id', '=', auth()->user()->id)
            ->where('financial_year_id', '=', $year->id)
            ->where('declaration_type_id', '=', $declaration->id)
            ->first();

        $data->update([
            'flag' => $request->input('flag')
        ]);

        $response = ['statusCode' => 200, 'message' => 'Umefanikiwa kutuma '.$declaration->type.' Sekretarieti ya maadili, Ahsante.', 'data' => $data];

        return response()->json($response, 200);
    }

    public function previewAdf(Request $request): JsonResponse
    {

        $year = Financial_year::where('is_active', '=', 1)->first();

        $declaration = User_declaration::with([
            'declaration_type' => function ($query) {
                $query->with([
                    'sections' => function ($qry) {
                        $qry->select('section_name', 'table_name');

                    }
                ]);
            },
            'user' => function ($query) {
                $query->select('id', 'file_number', 'first_name', 'middle_name', 'last_name', 'nida', 'phone_number');
            },
        ])
            ->where('declaration_type_id','=', $request->declaration_type)
            ->where('user_id', '=', auth()->user()->id)
            ->where('financial_year_id', '=', $year->id)
            ->first();

        if ($declaration == null){

            $response = ['statusCode' => 400, 'message' => "Auna data yeyote ambayo umejaza kwenye tamko hili,tafadhali jaza kwanza taarifa ili uweze kupata taarifa husika la tamko lako"];

            return response()->json($response, 200);
        }

        foreach ($declaration->declaration_type->sections as $section) {

            $data = DB::table(strtolower($section->table_name))
                ->get();

            $requirements = DB::table('requirements')
                ->join('section_requirements','requirements.id','=','section_requirements.requirement_id')
                ->join('sections','section_requirements.section_id','=','sections.id')
                ->where('sections.table_name','=',$section->table_name)
                ->select('requirements.id','requirements.label','requirements.field_name','requirements.field_type')
                ->get();

            $section->section_data = $data;
            $section->requirements = $requirements;
        }

        $response = ['statusCode' => 200, 'declaration' => $declaration, 'year' => $year->year];

        return response()->json($response, 200);
    }

    public function confirmDeclarationPreview(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'adf_token' =>  ['required','uuid'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }



        $adf_token = $request->input('adf_token');

        $update = User_declaration::where('secure_token','=',$adf_token)->first();

        if ($update == null){

            $response = ['statusCode' => 400, 'message' => 'ADF token '.$adf_token.' haipo, Ahsante'];
            return response()->json($response, 200);
        }

        $update->is_confirmed = true;
        $update->save();

        $response = ['statusCode' => 200, 'message' => 'Umefanikiwa kuthibitisha, sasa unaweza kuwasilisha tamko hili Sekretarieti ya maadili, Ahsante','data' => $update];
        return response()->json($response, 200);
    }

    public function downloadAdf(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'user_declaration' =>  ['required','integer'],
            'password' =>  ['required','string','min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }


        $user_declaration = $request->input('user_declaration');
        $password = $request->input('password');

        $download = Declaration_download::create([
            'secure_token' => Str::uuid(),
            'downloader_secure_token' => auth()->user()->secure_token,
            'user_declaration_id' => $user_declaration,
            'password' => encrypt($password)
        ]);

        $response = ['statusCode' => 200, 'password' => decrypt($download->password)];

        return response()->json($response, 200);
    }

    public function ADFDownloadHistory(){

        $download_histories = Declaration_download::with([
            'user_declaration' => function($query){
             $query->with([
                 'declaration_type' => function($qry){
                   $qry->select('id','type');
                 }
             ])
                 ->select('id','declaration_type_id','adf_number');
            },
        ])
        ->where('downloader_secure_token','=',auth()->user()->secure_token)
            ->select('id','secure_token','user_declaration_id','password')
            ->get();

        $response = ['statusCode' => 200, 'data' => $download_histories];

        return response()->json($response, 200);
    }

    public function getDeclarationReceipt(Request $request): JsonResponse
    {

        $active_year = Financial_year::where('is_active', '=', 1)->first();

        $declaration = User_declaration::with([
            'declaration_type',
            'user' => function ($query) {
                $query->select('id', 'file_number', 'first_name', 'middle_name', 'last_name', 'nida', 'phone_number');
            },
        ])
            ->where('declaration_type_id','=', $request->declaration_type)
            ->where('user_id', '=', auth()->user()->id)
            ->where('financial_year_id', '=', $active_year->id)
            ->first();

        if ($declaration == null){

            $response = ['statusCode' => 400, 'message' => "Auna data yeyote ambayo umejaza kwenye tamko hili,tafadhali jaza kwanza taarifa ili uweze kupata taarifa husika la tamko lako"];

            return response()->json($response, 200);
        }

        $response = ['statusCode' => 200, 'message' => "Umefanikiwa kutuma tamko lako lenye kumbukumbu namba: ".$declaration->adf_number." Sekretarieti ya maadili.", 'declaration' => $declaration, 'year' => $active_year->year];

        return response()->json($response, 200);
    }

    private function generateAdfNumber($declarationCode, $year): string
    {
        return 'ADF' . '-' . $declarationCode . '-' . $year . '-' . mt_rand(100, 999);
    }

    private function generateADFPassword(): string
    {

        return Str::random(10);
    }

    /**
     * @param mixed $sections
     * @param $check
     * @return JsonResponse
     */
    private function insertSections(mixed $sections, $check): JsonResponse
    {

        $array = [];

        foreach ($sections as $section) {


            $table = strtolower($section['section']['table']);
            if (count($section['section']['data']) > 0) {

                foreach ($section['section']['data'] as $values) {

                    $new_object = new stdClass();
                    $object = new stdClass();
                    $object->user_declaration_id = $check->id;
                    foreach ($values as $key => $value) {
                        $object->$key = $value;
                        $new_object = $object;
                    }

                    $array[] = $new_object;

                    $encode = json_encode($array, 1);
                    $row = json_decode($encode, true);

                     DB::table($table)->insert($row);

                    $data = DB::table($table)->orderBy('id','DESC')->first();

                    $response = ['statusCode' => 200, 'message' => 'Umefanikiwa kutuma taarifa za tamko kikamilifu', 'table' => $table,'data' => $data];

                    return response()->json($response);
                }

            }
        }

    }

}
