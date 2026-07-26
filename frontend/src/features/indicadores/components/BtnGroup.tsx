export default function BtnGroup({
  label,
  options,
  value,
  onChange,
}: {
  label: string;
  options: string[];
  value: string;
  onChange: (v: string) => void;
}) {
  return (
    <div>
      <label
        className="block text-xs font-bold uppercase tracking-widest mb-1.5"
        style={{ color: '#5A7295' }}
      >
        {label}
      </label>
      <div className="flex gap-2">
        {options.map((opt) => (
          <button
            key={opt}
            type="button"
            onClick={() => onChange(opt)}
            className="flex-1 py-2 rounded-xl text-sm font-semibold border transition-all"
            style={{
              background: value === opt ? '#1B3A6B' : '#F4F7FB',
              color: value === opt ? '#fff' : '#374151',
              borderColor: value === opt ? '#1B3A6B' : 'rgba(27,58,107,0.2)',
            }}
          >
            {opt}
          </button>
        ))}
      </div>
    </div>
  );
}
