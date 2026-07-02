import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { AlertCircle, ArrowLeft, CheckCircle2, Eye, EyeOff, Scissors, ShieldCheck, Ticket } from 'lucide-react';
import { motion } from 'motion/react';
import { toast } from 'sonner';
import { authService } from '../services/auth-service';

function FieldError({ message }) {
  if (!message) {
    return null;
  }

  return <p className="field-error">{message}</p>;
}

function getSafeRedirectTarget(from) {
  if (typeof from !== 'string' || !from.startsWith('/') || from.startsWith('//')) {
    return '/dashboard';
  }

  return from === '/login' || from === '/cadastro' ? '/dashboard' : from;
}

function TicketSubmitButton({ isLoading }) {
  const [isHovering, setIsHovering] = useState(false);
  const isCutting = isHovering || isLoading;

  return (
    <motion.button
      type="submit"
      disabled={isLoading}
      aria-busy={isLoading}
      onHoverStart={() => setIsHovering(true)}
      onHoverEnd={() => setIsHovering(false)}
      animate={isLoading ? { y: 18, rotate: 1 } : { y: 0, rotate: 0 }}
      whileHover={isLoading ? undefined : { y: 6, rotate: 0.3 }}
      whileTap={isLoading ? undefined : { y: 12, rotate: 0.6 }}
      transition={{ type: 'spring', stiffness: 220, damping: 18 }}
      className="ticket-submit"
    >
      <span className="ticket-hole left" />
      <span className="ticket-hole right" />

      <motion.span
        className="ticket-scissors"
        animate={isCutting ? { opacity: 1, y: 0, scaleX: 1 } : { opacity: 0, y: 10, scaleX: 0.86 }}
        transition={{ duration: 0.22, ease: 'easeOut' }}
      >
        <span />
        <Scissors size={20} />
        <span />
      </motion.span>

      <span className="ticket-submit-grid">
        <span>
          <span className="ticket-kicker">Canhoto destacável</span>
          <span className="ticket-title">{isLoading ? 'Validando acesso' : 'Entrar no estádio'}</span>
          <span className="ticket-copy">{isLoading ? 'Conferindo ingresso...' : 'Passe o mouse para validar'}</span>
        </span>

        <span className="barcode-box">
          <span className="barcode-lines">
            {[4, 2, 1, 3, 1, 4, 2, 2, 1, 3, 4, 1, 2, 3, 1, 4, 2, 1, 4, 2].map((width, index) => (
              <span key={`${width}-${index}`} style={{ width }} />
            ))}
          </span>
          <span className="barcode-number">0 51000 01251 7</span>
          <motion.span
            className="barcode-redaction"
            animate={isLoading ? { opacity: 0.95 } : isHovering ? { opacity: 0.34 } : { opacity: 0 }}
            transition={{ duration: 0.24 }}
          />
        </span>
      </span>
    </motion.button>
  );
}

export function LoginPage({ onAuthenticated }) {
  const location = useLocation();
  const navigate = useNavigate();
  const [isLoading, setIsLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const redirectTarget = getSafeRedirectTarget(location.state?.from);
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm({ mode: 'onBlur' });

  const onSubmit = async (data) => {
    setIsLoading(true);
    setErrorMsg('');

    try {
      const response = await authService.login(data);
      onAuthenticated(response.data.user ?? null);
      toast.success('Login realizado com sucesso!');
      navigate(redirectTarget, { replace: true });
    } catch (error) {
      const serverErrors = error.payload?.errors ?? {};

      Object.entries(serverErrors).forEach(([field, messages]) => {
        if (['email', 'password'].includes(field)) {
          setError(field, {
            type: 'server',
            message: messages[0],
          });
        }
      });

      setErrorMsg(error.message || 'Não foi possível entrar com os dados informados.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="auth-split-page login-page">
      <div className="login-hero-panel">
        <div className="login-hero-content">
          <Link to="/" className="auth-back-link">
            <ArrowLeft size={16} />
            Voltar ao início
          </Link>

          <div className="hero-border-block">
            <span className="auth-pill">
              <Ticket size={18} />
              Entrada CopaZone
            </span>
            <h2>Apresente seu ingresso na catraca.</h2>
            <p>Suas ligas, palpites da Copa e ranking ficam logo depois do portão.</p>
          </div>

          <div className="auth-step-list">
            {[
              { icon: Ticket, title: '1. Informe seu ingresso', desc: 'Use o e-mail cadastrado como titular da entrada.' },
              { icon: ShieldCheck, title: '2. Valide o código', desc: 'Digite sua senha para confirmar o acesso.' },
              { icon: CheckCircle2, title: '3. Entre na Copa', desc: 'A catraca libera seu painel de ligas e palpites.' },
            ].map((step) => {
              const StepIcon = step.icon;

              return (
                <div key={step.title} className="auth-step">
                  <div className="auth-step-icon active">
                    <StepIcon size={20} />
                  </div>
                  <div>
                    <h3>{step.title}</h3>
                    <p>{step.desc}</p>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      <div className="auth-form-panel">
        <Link to="/" className="auth-mobile-back">
          <ArrowLeft size={16} />
          Voltar
        </Link>

        <motion.div
          initial={{ opacity: 0, scale: 0.96 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.45 }}
          className="auth-card-wrap"
        >
          <form onSubmit={handleSubmit(onSubmit)} className="ticket-form" noValidate>
            <div className="ticket-card">
              <div className="ticket-card-header">
                <div>
                  <div className="ticket-kicker">Ingresso digital</div>
                  <h1>CopaZone</h1>
                  <p>Acesso ao painel da sua Copa.</p>
                </div>
                <Ticket size={38} />
              </div>

              <div className="ticket-card-body">
                {errorMsg && (
                  <div className="form-alert error">
                    <AlertCircle size={17} />
                    <span>{errorMsg}</span>
                  </div>
                )}

                <div className="field-stack">
                  <label>Titular do ingresso</label>
                  <input
                    type="email"
                    {...register('email', {
                      required: 'Informe seu e-mail para entrar.',
                      pattern: {
                        value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                        message: 'Informe um e-mail válido.',
                      },
                    })}
                    aria-invalid={!!errors.email}
                    placeholder="seu@email.com"
                  />
                  <FieldError message={errors.email?.message} />
                </div>

                <div className="field-stack">
                  <label>Código de acesso</label>
                  <div className="password-field">
                    <input
                      type={showPassword ? 'text' : 'password'}
                      {...register('password', {
                        required: 'Informe sua senha para entrar.',
                      })}
                      aria-invalid={!!errors.password}
                      placeholder="********"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((value) => !value)}
                      aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                    >
                      {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                    </button>
                  </div>
                  <FieldError message={errors.password?.message} />
                </div>

                <div className="ticket-meta-grid">
                  <div>
                    <span>Portão</span>
                    <strong>07</strong>
                  </div>
                  <div>
                    <span>Fila</span>
                    <strong>CZ</strong>
                  </div>
                  <div>
                    <span>Assento</span>
                    <strong>APP</strong>
                  </div>
                </div>

                <p className="auth-switch">
                  Ainda não tem ingresso? <Link to="/cadastro">Criar conta grátis</Link>
                </p>
              </div>
            </div>

            <TicketSubmitButton isLoading={isLoading} />
          </form>
        </motion.div>
      </div>
    </div>
  );
}

