export function minorToPesoInput(minor: string): string {
    const amount = BigInt(minor);
    return `${amount / 100n}.${(amount % 100n).toString().padStart(2, "0")}`;
}

export function formatPesos(minor: string): string {
    const amount = BigInt(minor);
    return `₱${(amount / 100n).toLocaleString()}.${(amount % 100n).toString().padStart(2, "0")}`;
}
