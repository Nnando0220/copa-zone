import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { ArrowLeft, ArrowRight, CheckCircle2, Globe2, LockKeyhole, Minus, Plus, ShieldCheck, Trophy, Users } from 'lucide-react';
import { motion } from 'motion/react';
import { toast } from 'sonner';
import { portalService } from '../services/portal-service';

const MIN_MEMBERS = 2;
const MAX_MEMBERS = 32;
const NAME_MAX = 32;
const suggestedLimits = [8, 16, 24, 32];

const initialLeagueForm = {
  name: '',
  visibility: 'private',
  max_members: 20,
};

function normalizeLeagueName(value) {
  return value.replace(/\s+/g, ' ').trim();
}

function clampCapacity(value) {
  const numericValue = Number.parseInt(value, 10);

  if (Number.isNaN(numericValue)) {
    return MIN_MEMBERS;
  }

  return Math.min(MAX_MEMBERS, Math.max(MIN_MEMBERS, numericValue));
}

function visibilityCopy(visibility) {
  return visibility === 'private'
    ? 'Apenas pessoas convidadas podem entrar na liga.'
    : 'Qualquer pessoa pode encontrar e solicitar entrada na liga.';
}

export function CreateLeaguePage() {
  const navigate = useNavigate();
  const [form, setForm] = useState(initialLeagueForm);
  const [fieldErrors, setFieldErrors] = useState({});
  const [isCreating, setIsCreating] = useState(false);
  const [error, setError] = useState('');

  const normalizedName = normalizeLeagueName(form.name);
  const previewName = normalizedName || 'Nome da liga';
  const nameCount = normalizeLeagueName(form.name).length;

  const previewRows = useMemo(() => ([
    {
      icon: Trophy,
      label: 'Nome da liga',
      value: previewName,
    },
    {
      icon: form.visibility === 'private' ? LockKeyhole : Globe2,
      label: 'Privacidade',
      value: form.visibility === 'private' ? 'Privada' : 'Publica',
      badge: form.visibility === 'private' ? 'Privada' : 'Publica',
    },
    {
      icon: Users,
      label: 'Limite de inscritos',
      value: `${form.max_members} participantes`,
    },
  ]), [form.max_members, form.visibility, previewName]);

  function updateField(event) {
    const { name, value } = event.target;

    setFieldErrors((current) => ({ ...current, [name]: '' }));
    setError('');
    setForm((current) => ({
      ...current,
      [name]: name === 'max_members' ? clampCapacity(value) : value,
    }));
  }

  function setVisibility(visibility) {
    setFieldErrors((current) => ({ ...current, visibility: '' }));
    setError('');
    setForm((current) => ({ ...current, visibility }));
  }

  function setCapacity(value) {
    setFieldErrors((current) => ({ ...current, max_members: '' }));
    setError('');
    setForm((current) => ({ ...current, max_members: clampCapacity(value) }));
  }

  function changeCapacity(delta) {
    setCapacity(form.max_members + delta);
  }

  function validateForm() {
    const nextErrors = {};

    if (normalizedName.length < 3) {
      nextErrors.name = 'Informe um nome com pelo menos 3 caracteres.';
    }

    if (normalizedName.length > NAME_MAX) {
      nextErrors.name = `O nome deve ter no maximo ${NAME_MAX} caracteres.`;
    }

    if (!['public', 'private'].includes(form.visibility)) {
      nextErrors.visibility = 'Escolha se a liga sera publica ou privada.';
    }

    if (form.max_members < MIN_MEMBERS || form.max_members > MAX_MEMBERS) {
      nextErrors.max_members = `Use um limite entre ${MIN_MEMBERS} e ${MAX_MEMBERS} participantes.`;
    }

    setFieldErrors(nextErrors);

    return Object.keys(nextErrors).length === 0;
  }

  async function submitLeague(event) {
    event.preventDefault();

    if (!validateForm()) {
      return;
    }

    setIsCreating(true);
    setError('');

    try {
      const payload = await portalService.createLeague({
        name: normalizedName,
        visibility: form.visibility,
        max_members: form.max_members,
      });
      const league = payload.data.league;

      toast.success('Sua liga esta pronta!');
      navigate(`/ligas/${league.id}`);
    } catch (requestError) {
      setError(requestError.message || 'Nao foi possivel criar a liga.');
    } finally {
      setIsCreating(false);
    }
  }

  return (
    <section className="create-league-page modern-create-page">
      <motion.div
        className="modern-create-header"
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.28 }}
      >
        <div>
          <p className="eyebrow">Nova liga</p>
          <h2>Configure sua liga da Copa</h2>
          <p>Defina nome, privacidade e limite de participantes em uma tela simples antes de convidar a turma.</p>
        </div>
        <Link className="join-league-back" to="/ligas/minhas">
          <ArrowLeft size={17} />
          Voltar
        </Link>
      </motion.div>

      <motion.form
        className="modern-create-layout"
        onSubmit={submitLeague}
        autoComplete="off"
        noValidate
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.28, delay: 0.04 }}
      >
        <section className="modern-create-panel">
          <div className="modern-create-section-title">
            <h3>Informacoes da liga</h3>
            <p>Defina como os participantes vao encontrar e entrar na sua liga.</p>
          </div>

          <label className="modern-field-stack" htmlFor="league-name">
            <span>Nome da liga</span>
            <div className="modern-text-input">
              <input
                id="league-name"
                name="name"
                value={form.name}
                onChange={updateField}
                onBlur={() => setForm((current) => ({ ...current, name: normalizeLeagueName(current.name) }))}
                minLength={3}
                maxLength={NAME_MAX}
                placeholder="Galacticos FC"
                aria-invalid={Boolean(fieldErrors.name)}
                aria-describedby={fieldErrors.name ? 'league-name-error' : undefined}
                required
              />
              <small>{nameCount}/{NAME_MAX}</small>
            </div>
            {fieldErrors.name && <p id="league-name-error" className="field-error">{fieldErrors.name}</p>}
          </label>

          <fieldset className="modern-privacy-field" aria-describedby={fieldErrors.visibility ? 'league-visibility-error' : undefined}>
            <legend>Privacidade</legend>
            <p>Escolha quem pode encontrar e entrar na sua liga.</p>
            <div className="modern-privacy-grid">
              <button
                type="button"
                className={form.visibility === 'private' ? 'active' : undefined}
                onClick={() => setVisibility('private')}
                aria-pressed={form.visibility === 'private'}
              >
                <span className="modern-choice-icon"><LockKeyhole size={20} /></span>
                <span>
                  <strong>Privada</strong>
                  <small>Apenas pessoas convidadas podem entrar na liga.</small>
                </span>
                {form.visibility === 'private' && <CheckCircle2 size={18} />}
              </button>
              <button
                type="button"
                className={form.visibility === 'public' ? 'active' : undefined}
                onClick={() => setVisibility('public')}
                aria-pressed={form.visibility === 'public'}
              >
                <span className="modern-choice-icon"><Globe2 size={20} /></span>
                <span>
                  <strong>Publica</strong>
                  <small>Qualquer pessoa pode encontrar e solicitar entrada.</small>
                </span>
                {form.visibility === 'public' && <CheckCircle2 size={18} />}
              </button>
            </div>
            {fieldErrors.visibility && <p id="league-visibility-error" className="field-error">{fieldErrors.visibility}</p>}
          </fieldset>

          <div className="modern-capacity-field">
            <div className="modern-create-section-title compact">
              <h3>Limite de inscritos</h3>
              <p>Defina o maximo de participantes da sua liga.</p>
            </div>
            <div className="modern-capacity-control">
              <button type="button" onClick={() => changeCapacity(-1)} aria-label="Diminuir limite">
                <Minus size={17} />
              </button>
              <input
                id="league-capacity"
                name="max_members"
                type="number"
                min={MIN_MEMBERS}
                max={MAX_MEMBERS}
                value={form.max_members}
                onChange={updateField}
                onBlur={(event) => setCapacity(event.target.value)}
                aria-invalid={Boolean(fieldErrors.max_members)}
                required
              />
              <button type="button" onClick={() => changeCapacity(1)} aria-label="Aumentar limite">
                <Plus size={17} />
              </button>
            </div>
            <div className="modern-capacity-meta">
              <span>Minimo: {MIN_MEMBERS}</span>
              <span>Maximo: {MAX_MEMBERS}</span>
            </div>
            <div className="modern-capacity-chips" aria-label="Limites sugeridos">
              {suggestedLimits.map((limit) => (
                <button key={limit} type="button" onClick={() => setCapacity(limit)}>
                  {limit}
                </button>
              ))}
            </div>
            {fieldErrors.max_members && <p className="field-error">{fieldErrors.max_members}</p>}
          </div>

          {error && <div className="content-error compact">{error}</div>}

          <button type="submit" className="primary-action modern-create-submit" disabled={isCreating}>
            {isCreating ? 'Criando liga...' : 'Criar liga'}
            <ArrowRight size={18} />
          </button>
        </section>

        <aside className="modern-create-preview" aria-label="Previa da liga">
          <div className="modern-create-section-title">
            <h3>Previa da liga</h3>
            <p>Veja como sua liga ficara para os participantes.</p>
          </div>

          <div className="league-preview-crest" aria-hidden="true">
            <ShieldCheck size={72} />
          </div>

          <div className="modern-preview-card">
            {previewRows.map((row) => {
              const Icon = row.icon;

              return (
                <article key={row.label}>
                  <span><Icon size={16} /> {row.label}</span>
                  <strong>
                    {row.badge ? <em>{row.badge}</em> : null}
                    {row.value}
                  </strong>
                </article>
              );
            })}
          </div>

          <div className="modern-preview-note">
            <ShieldCheck size={18} />
            <span>{visibilityCopy(form.visibility)}</span>
          </div>
        </aside>
      </motion.form>
    </section>
  );
}
