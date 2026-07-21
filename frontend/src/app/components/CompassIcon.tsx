interface CompassIconProps {
  size?: number;
}

export default function CompassIcon({
  size = 36,
}: CompassIconProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 40 40"
      fill="none"
    >
      <path
        d="M20 3 L23 15 L20 13 L17 15 Z"
        fill="#B91C1C"
      />

      <path
        d="M20 37 L23 25 L20 27 L17 25 Z"
        fill="#B91C1C"
      />

      <path
        d="M3 20 L15 17 L13 20 L15 23 Z"
        fill="#B91C1C"
      />

      <path
        d="M37 20 L25 17 L27 20 L25 23 Z"
        fill="#B91C1C"
      />

      <circle
        cx="20"
        cy="20"
        r="4"
        fill="#B91C1C"
      />
    </svg>
  );
}