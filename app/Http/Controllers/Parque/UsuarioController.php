<?php

namespace App\Http\Controllers\Parque;

use App\Http\Controllers\Controller;
use App\Jobs\processEmail;
use App\Jobs\processSMS;
use App\Jobs\processVerify;
use App\Mail\sendMail;
use App\Models\ModelosParque\Tarjeta;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    public function crearDueño(Request $request)
    {
        $validacion = Validator::make(
            $request->all(), [
                'nombre' => "required|string|max:20",
                'apellidos' => "required|string|max:30",
                'edad' => "required|integer|min:1|max:120",
                'email' => "required|string|unique:users|email",
                'contraseña' => "required|string|min:4",
                'telefono' => "required|integer",
                'username' => "required|string|max:20",
            ]
        );

        if ($validacion->fails()) {
            return response()->json([
                "status" => 400,
                "msg" => "No se cumplieron las validaciones",
                "error" => $validacion->errors(),
                "data" => null,
            ], 400);
        }

        $tarjeta = new Tarjeta();
        $tarjeta->save();

        srand(time());
        $numero_aleatorio = rand(5000, 6000);

        $user = new User();
        $user->nombre = $request->nombre;
        $user->apellidos = $request->apellidos;
        $user->edad = $request->edad;
        $user->email = $request->email;
        $user->contraseña = bcrypt($request->contraseña);
        $user->telefono = $request->telefono;
        $user->username = $request->username;
        $user->codigo = $numero_aleatorio;
        $user->numero_tarjeta = $tarjeta->id;
        $user->save();

        $valor = $user->id;
        $url = URL::temporarySignedRoute(
            'validarnumero', now()->addMinutes(30), ['url' => $valor]);

            Mail::to($user->email)->send(new sendMail($user, $url)); 

        if ($user->save()) {
            return response()->json([
                "status" => 201,
                "msg" => "Se ha registrado de manera satisfactoria",
                "error" => null,
                "data" => $user,
            ], 201);
        } else {
            return response()->json([
                "msg" => "Existe un error al registrar el usuario, por favor verifique que sea la informacion adecuada",
            ]);
        }

    }

    public function InicioSesion(Request $request)
    {
        $validacion = Validator::make(
            $request->all(),
            [
                'email' => 'required|email',
                'contraseña' => 'required',
            ]);

        if ($validacion->fails()) {
            return response()->json([
                'status' => false,
                'msg' => 'Error en las validaciones',
                'error' => $validacion->errors(),
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->contraseña, $user->contraseña)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token= $user->createToken("auth_token")->plainTextToken;
        return response()->json([
            'msg' => "Inicio sesion correctamente",
            'token'=> $token
        ], 200);

    }

    public function logout(Request $request){
        $request->user()->tokens()->delete();
        return response()->json([
            "status"        => 200,
            "msg"           => "Has salido de la sesion de manera adecuada"
        ], 200);
    }

    public function numerodeverificacionmovil(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(401, "EL CODIGO ES INCORRECTO");
        }
        srand(time());

       // $numero_aleatorio2 = rand(5000, 6000);
        $numeroiddelaurl = $request->url;

      
        $user = User::where('id', $numeroiddelaurl)->first();
        
        Http::withBasicAuth('AC83939905e7c70332ed1adf2ce5eba13e', '45a9c20b8c6ce9f5b2ff260941555113')
        ->asForm()
        ->post('https://api.twilio.com/2010-04-01/Accounts/AC83939905e7c70332ed1adf2ce5eba13e/Messages.json',[
           
            'To'=>"whatsapp:+521{$user->telefono}",
            'From'=>'whatsapp:+14155238886',
            'Body'=>"Tu codigo de verificacion es: {$user->codigo}",
        ]);

     //   processVerify::dispatch($user, $url)->onQueue('processVerify')->onConnection('database')->delay(now()->addSeconds(15));

        return response()->json([
            "msg" => "Tu numero de verificacion a sido enviada a tu telefono. ",

        ], 201);
    }

    public function registrarSMS(Request $request)

    
    {
        $validacion = Validator::make($request->all(), [

            'codigo' => 'required|digits:4',

        ]);
  
        if ($validacion->fails()) {
            return response()->json([
                "error" => $validacion->errors(),

            ], 400);
        }
        $user = User::where('codigo', $request->codigo)->first();

        if ($user->codigo == $request->codigo) {

            if (!$user) {
                abort(401, "Usuario no encontrado");
            }
            $id = $user->id;
            $userupdate = User::find($id);
            $userupdate->status = 1;
            $userupdate->save;
            if ($userupdate->save()) {
                return response()->json([
                    "msg" => "tu sesion ha sido actualizada",
                    "usuario" => $userupdate,

                ], 201);
            }

        } else {
            return response()->json([
                "mensage" => "Codigo invalido",

            ], 401);
        }

    }
    public function UserInfo($id)
    {
        $user = DB::table('users')->where('id', $id)->exists();
        if ($user) {
            $user = User::find($id);
            if ($user) {
                return response()->json([
                    'msg' => 'Se encontro la informacion',
                    'data' => $user,
                ]);
            } 
        } else {
            return response()->json([
                'info' => null,
                'msg' => 'No se encontro informacion',
            ]);
        }
    }
}