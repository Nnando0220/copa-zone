<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
            ],
            'meta' => [],
            'message' => 'Cadastro realizado com sucesso.',
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => $this->translatedMessage('auth.failed', 'E-mail ou senha incorretos.'),
            ]);
        }

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return response()->json([
            'data' => [
                'user' => new UserResource($request->user()),
            ],
            'meta' => [],
            'message' => 'Sessão iniciada com sucesso.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'data' => null,
            'meta' => [],
            'message' => 'Sessão encerrada com sucesso.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['user' => new UserResource($request->user())],
            'meta' => [],
            'message' => 'Usuário autenticado.',
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'data' => ['user' => new UserResource($user->fresh())],
            'meta' => [],
            'message' => 'Perfil atualizado com sucesso.',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return response()->json([
            'data' => null,
            'meta' => [],
            'message' => 'Se este e-mail existir, enviaremos instruções de recuperação.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => $this->passwordResetStatusMessage($status),
            ]);
        }

        return response()->json([
            'data' => null,
            'meta' => [],
            'message' => 'Senha redefinida com sucesso.',
        ]);
    }

    private function translatedMessage(string $key, string $fallback): string
    {
        $translated = __($key);
        $defaultEnglishMessages = [
            'auth.failed' => 'These credentials do not match our records.',
            Password::INVALID_TOKEN => 'This password reset token is invalid.',
            Password::INVALID_USER => "We can't find a user with that email address.",
            Password::RESET_THROTTLED => 'Please wait before retrying.',
        ];

        return $translated === $key || ($defaultEnglishMessages[$key] ?? null) === $translated
            ? $fallback
            : $translated;
    }

    private function passwordResetStatusMessage(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => $this->translatedMessage($status, 'O link para redefinir a senha é inválido ou expirou.'),
            Password::INVALID_USER => $this->translatedMessage($status, 'Não encontramos um usuário com esse e-mail.'),
            Password::RESET_THROTTLED => $this->translatedMessage($status, 'Aguarde alguns instantes antes de tentar novamente.'),
            default => $this->translatedMessage($status, 'Não foi possível redefinir a senha com os dados informados.'),
        };
    }
}
