import { Link } from 'react-router';
import { ArrowLeft, ShieldCheck } from 'lucide-react';

export function AuthStepsSidebar({ title, subtitle, steps }) {
  return (
    <aside className="auth-steps-sidebar">
      <div className="auth-steps-content">
        <Link to="/" className="auth-back-link">
          <ArrowLeft size={16} />
          Voltar ao inicio
        </Link>

        <div className="auth-side-heading">
          <span className="auth-pill">
            <ShieldCheck size={18} />
            CopaZone seguro
          </span>
          <h2>{title}</h2>
          <p>{subtitle}</p>
        </div>

        <div className="auth-step-list">
          {steps.map((step) => {
            const StepIcon = step.icon;

            return (
              <div key={step.title} className="auth-step">
                <div className={step.active ? 'auth-step-icon active' : 'auth-step-icon'}>
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
    </aside>
  );
}

