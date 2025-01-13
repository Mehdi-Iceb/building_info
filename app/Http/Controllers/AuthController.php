<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'=> 'required|Regex:/^[\D]+$/i|unique:users, name|max:100',
            'email'=> 'required|email:rfc|max:255|unique:users,email',
            'password' => 'required',
        ]);

        DB::beginTransaction();

        try{

            $user = new User();

            $user->name = $request->name;
            $user->email = strtolower($request->email);
            $user->password = bcrypt($request->password, [
                'rounds' => 12
            ]);

        }catch(Exception $e){
            return response()->json($e);
        }
    }

    public function login(Request $request)
    {
        
        if(auth()->attempt(array('email' => $request->email,'password' => $request->password)) || auth()->attempt(array('name' => $request->email,'password' => $request->password))){
            
            $user = User::where('id', '=', auth()->user()->id)->first();

            if ($user->email_verified_at != null){
                $token = $user->createToken('buildingapi');
            }else {
                $token = "";
            }

            if ($token == ""){
                return response()->json([
                    'status_code' => 200,
                    'status_message' => 'Utilisateur connecté',
                    'user' => $user,
                    'token'=>""
                ]);
            }

            
            return response()->json([
                'status_code' => 200,
                'status_message' => 'Utilisateur connecté',
                'user' => $user,
                'token' => $token->plainTextToken
            ]);

        }else{

            return response()->json([
                'status_code' => 403,
                'status_message' => 'informations non valide.'
            ]);
        }
    }
}
