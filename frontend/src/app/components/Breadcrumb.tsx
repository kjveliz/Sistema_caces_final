interface BreadcrumbProps {
  items: string[];
}

export default function Breadcrumb({
  items,
}: BreadcrumbProps) {
  return (
    <div className="flex items-center gap-2 flex-wrap mb-6">
      {items.map((item, index) => {
        const isCurrent = index === items.length - 1;

        return (
          <div
            key={`${item}-${index}`}
            className="flex items-center gap-2"
          >
            {index > 0 && (
              <span style={{ color: "#D1D5DB" }}>
                ›
              </span>
            )}

            <span
              className="text-xs px-2.5 py-1 rounded-full font-semibold"
              style={{
                background: isCurrent
                  ? "#1B3A6B"
                  : "#EEF2F7",
                color: isCurrent
                  ? "#fff"
                  : "#5A7295",
              }}
            >
              {item}
            </span>
          </div>
        );
      })}
    </div>
  );
}