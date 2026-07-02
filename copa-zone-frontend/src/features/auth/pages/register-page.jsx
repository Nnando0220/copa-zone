import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { AlertCircle, ArrowLeft, ArrowRight, Eye, EyeOff, Trophy, UserPlus, Users } from 'lucide-react';
import { motion } from 'motion/react';
import { toast } from 'sonner';
import { AuthStepsSidebar } from '../components/auth-steps-sidebar';
import { authService } from '../services/auth-service';

function FieldError({ message }) {
  if (!message) {
    return null;
  }

  return <p className="field-error">{message}</p>;
}

export function RegisterPage({ onAuthenticated }) {
  const navigate = useNavigate();
  const [isLoading, setIsLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [signatureDraft, setSignatureDraft] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
  const {
    register,
    handleSubmit,
    setError,
    watch,
    formState: { errors },
  } = useForm({ mode: 'onBlur' });

  const watchedName = watch('name');
  const watchedPassword = watch('password');
  const signatureName = watchedName?.trim() || 'Sua assinatura';

  useEffect(() => {
    if (!isLoading) {
      setSignatureDraft('');
      return undefined;
    }

    let index = 0;
    setSignatureDraft('');

    const timer = window.setInterval(() => {
      index += 1;
      setSignatureDraft(signatureName.slice(0, index));

      if (index >= signatureName.length) {
        window.clearInterval(timer);
      }
    }, 45);

    return () => window.clearInterval(timer);
  }, [isLoading, signatureName]);

  const onSubmit = async (data) => {
    setIsLoading(true);
    setFormError('');

    try {
      const response = await authService.register({
        name: data.name,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
      });

      onAuthenticated(response.data.user ?? null);
      toast.success('Cadastro realizado com sucesso!');
      navigate('/dashboard', { replace: true });
    } catch (error) {
      const serverErrors = error.payload?.errors ?? {};

      Object.entries(serverErrors).forEach(([field, messages]) => {
        if (['name', 'email', 'password', 'password_confirmation'].includes(field)) {
          setError(field, {
            type: 'server',
            message: messages[0],
          });
        }
      });

      setFormError(error.message || 'Revise os campos destacados para concluir sua inscrição.');
      toast.error('Revise os dados da inscrição.');
    } finally {
      setIsLoading(false);
    }
  };

  const steps = [
    { icon: UserPlus, title: '1. Crie sua conta', desc: 'Crie sua conta gratuitamente na CopaZone.', active: true },
    { icon: Users, title: '2. Entre em uma liga', desc: 'Junte-se aos seus amigos ou jogue globalmente.', active: false },
    { icon: Trophy, title: '3. Faça seus palpites', desc: 'Mostre que você entende da Copa do Mundo.', active: false },
  ];

  return (
    <div className="register-page">
      <AuthStepsSidebar
        title="Comece em 3 passos"
        subtitle="Você está a três passos de entrar no bolão da Copa."
        steps={steps}
      />

      <div className="register-form-panel">
        <div className="paper-grid" />

        <Link to="/" className="auth-mobile-back dark">
          <ArrowLeft size={16} />
          Voltar
        </Link>

        <div className="register-card-wrap">
          <motion.div initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} className="registration-card">
            <div className="registration-strip" />
            <div className="registration-header">
              <div>
                <div className="document-kicker">Cadastro</div>
                <h1>Crie sua conta</h1>
              </div>
              <div className="free-stamp">
                Conta
                <br />
                Grátis
              </div>
            </div>

            <form onSubmit={handleSubmit(onSubmit)} className="registration-form" noValidate>
              {formError && (
                <div className="form-alert error">
                  <AlertCircle size={17} />
                  <span>{formError}</span>
                </div>
              )}

              <div className="form-grid">
                <div className="field-stack underline-field">
                  <label>Nome completo</label>
                  <input
                    type="text"
                    {...register('name', {
                      required: 'Informe seu nome completo para criar a conta.',
                      maxLength: {
                        value: 120,
                        message: 'O nome completo pode ter no máximo 120 caracteres.',
                      },
                    })}
                    aria-invalid={!!errors.name}
                    placeholder="Joao Silva"
                  />
                  <FieldError message={errors.name?.message} />
                </div>

                <div className="field-stack underline-field">
                  <label>E-mail</label>
                  <input
                    type="email"
                    {...register('email', {
                      required: 'Informe seu e-mail para criar a conta.',
                      pattern: {
                        value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                        message: 'Informe um e-mail válido.',
                      },
                      maxLength: {
                        value: 180,
                        message: 'O e-mail pode ter no máximo 180 caracteres.',
                      },
                    })}
                    aria-invalid={!!errors.email}
                    placeholder="joao@exemplo.com"
                  />
                  <FieldError message={errors.email?.message} />
                </div>
              </div>

              <div className="form-grid">
                <div className="field-stack underline-field">
                  <label>Senha</label>
                  <div className="password-field minimal">
                    <input
                      type={showPassword ? 'text' : 'password'}
                      {...register('password', {
                        required: 'Crie uma senha para proteger sua conta.',
                        minLength: {
                          value: 8,
                          message: 'A senha precisa ter pelo menos 8 caracteres.',
                        },
                        validate: {
                          hasLetter: (value) => /\p{L}/u.test(value) || 'A senha precisa ter pelo menos uma letra.',
                          hasNumber: (value) => /\d/.test(value) || 'A senha precisa ter pelo menos um número.',
                        },
                      })}
                      aria-invalid={!!errors.password}
                      placeholder="********"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((value) => !value)}
                      aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                    >
                      {showPassword ? <EyeOff size={17} /> : <Eye size={17} />}
                    </button>
                  </div>
                  <FieldError message={errors.password?.message} />
                </div>

                <div className="field-stack underline-field">
                  <label>Confirmar senha</label>
                  <div className="password-field minimal">
                    <input
                      type={showPasswordConfirmation ? 'text' : 'password'}
                      {...register('password_confirmation', {
                        required: 'Confirme a senha para evitar erro de digitação.',
                        validate: (value) => value === watchedPassword || 'A confirmação precisa ser igual à senha.',
                      })}
                      aria-invalid={!!errors.password_confirmation}
                      placeholder="********"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPasswordConfirmation((value) => !value)}
                      aria-label={showPasswordConfirmation ? 'Ocultar confirmação de senha' : 'Mostrar confirmação de senha'}
                    >
                      {showPasswordConfirmation ? <EyeOff size={17} /> : <Eye size={17} />}
                    </button>
                  </div>
                  <FieldError message={errors.password_confirmation?.message} />
                </div>
              </div>

              <div className="password-note">
                A senha deve ter pelo menos 8 caracteres, uma letra, um número e a confirmação precisa ser idêntica.
              </div>

              <button type="submit" disabled={isLoading} className="signature-button">
                <span className="document-kicker">CopaZone</span>
                <span className="signature-line">
                  <span className={isLoading ? 'signature-name visible' : 'signature-name'}>
                    {isLoading ? signatureDraft : signatureName}
                    {isLoading && <span className="cursor-pulse">|</span>}
                  </span>
                  {!isLoading && <span className="signature-placeholder">passe o mouse para revelar a assinatura</span>}
                </span>
                <span className="signature-footer">
                  {isLoading ? 'Registrando assinatura...' : 'Clique para assinar e criar conta'}
                  <ArrowRight size={20} />
                </span>
              </button>
            </form>

            <p className="auth-switch">
              Já está inscrito na CopaZone? <Link to="/login">Acessar minha conta</Link>
            </p>
          </motion.div>
        </div>
      </div>
    </div>
  );
}

