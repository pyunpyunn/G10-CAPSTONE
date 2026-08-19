import { KeyRound, X } from 'lucide-react'
import Modal from '../ui/Modal'

const questionOptions = [
  ['first_pet', "What was your first pet's name?"],
  ['birth_city', "What's the name of the city where you were born?"],
  ['childhood_nickname', 'What was your childhood nickname?'],
  ['parents_met_city', "What's the name of the city where your parents met?"],
  ['eldest_cousin_first_name', "What's the first name of your eldest cousin?"],
  ['first_school', "What's the name of the first school you attended?"],
]

export default function RecoveryQuestionsModal({ isOpen, form, setForm, formError, isSaving, onClose, onSubmit }) {
  function updateField(field, value) {
    setForm((currentForm) => ({ ...currentForm, [field]: value }))
  }

  return (
    <Modal
      title="Account recovery questions"
      isOpen={isOpen}
      onClose={onClose}
      className="profile-modal"
      footer={(
        <>
          <button className="btn btn-secondary btn-sm" type="button" disabled={isSaving} onClick={onClose}><X size={14} /> Cancel</button>
          <button className="btn btn-primary btn-sm" type="submit" form="recovery-questions-form" disabled={isSaving}><KeyRound size={14} /> Save questions</button>
        </>
      )}
    >
      <form id="recovery-questions-form" className="profile-form-grid" onSubmit={onSubmit}>
        {formError && <div className="form-error full">{formError}</div>}
        <label className="full">
          <span>Current password</span>
          <input type="password" value={form.current_password} onChange={(event) => updateField('current_password', event.target.value)} placeholder="Verify your current password" />
        </label>
        <label>
          <span>Question 1</span>
          <select value={form.question_1} onChange={(event) => updateField('question_1', event.target.value)}>
            <option value="">Choose a question</option>
            {questionOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
        </label>
        <label>
          <span>Answer 1</span>
          <input type="text" value={form.answer_1} onChange={(event) => updateField('answer_1', event.target.value)} autoComplete="off" />
        </label>
        <label>
          <span>Question 2</span>
          <select value={form.question_2} onChange={(event) => updateField('question_2', event.target.value)}>
            <option value="">Choose a different question</option>
            {questionOptions.map(([value, label]) => <option key={value} value={value} disabled={value === form.question_1}>{label}</option>)}
          </select>
        </label>
        <label>
          <span>Answer 2</span>
          <input type="text" value={form.answer_2} onChange={(event) => updateField('answer_2', event.target.value)} autoComplete="off" />
        </label>
      </form>
    </Modal>
  )
}
