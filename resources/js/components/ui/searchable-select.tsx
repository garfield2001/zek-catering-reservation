import { useId, useMemo, useRef, useState } from "react";

export type SearchableSelectOption = {
    value: string | number;
    label: string;
    disabled?: boolean;
};

/** A bounded, keyboard-friendly picker for dynamic reference data. */
export function SearchableSelect({
    id,
    value,
    onChange,
    options,
    placeholder,
    searchPlaceholder = "Search options",
}: {
    id?: string;
    value: string | number | null;
    onChange: (value: string) => void;
    options: SearchableSelectOption[];
    placeholder: string;
    searchPlaceholder?: string;
}) {
    const generatedId = useId();
    const listId = `${id ?? generatedId}-options`;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");
    const [activeIndex, setActiveIndex] = useState(0);
    const [searching, setSearching] = useState(false);
    const blurTimer = useRef<number | null>(null);
    const selected = options.find(
        (option) => String(option.value) === String(value),
    );
    const shown = useMemo(() => {
        const term = query.trim().toLocaleLowerCase();

        return term
            ? options.filter((option) =>
                  option.label.toLocaleLowerCase().includes(term),
              )
            : options;
    }, [options, query]);
    const choose = (option: SearchableSelectOption) => {
        if (option.disabled) return;
        onChange(String(option.value));
        setQuery("");
        setOpen(false);
        setSearching(false);
    };
    const beginSearch = () => {
        if (blurTimer.current !== null) {
            window.clearTimeout(blurTimer.current);
            blurTimer.current = null;
        }
        setQuery("");
        setOpen(true);
        setActiveIndex(0);
        setSearching(true);
    };

    return (
        <div className="relative">
            <input
                id={id}
                role="combobox"
                aria-autocomplete="list"
                aria-controls={listId}
                aria-expanded={open}
                aria-activedescendant={
                    open ? `${listId}-${activeIndex}` : undefined
                }
                value={open && searching ? query : (selected?.label ?? "")}
                placeholder={open ? searchPlaceholder : placeholder}
                onFocus={() => beginSearch()}
                onClick={() => {
                    // A previously focused input does not fire focus again.
                    // Re-open it so a user can immediately choose another
                    // category after acting elsewhere in the form.
                    beginSearch();
                }}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                    setActiveIndex(0);
                    setSearching(true);
                }}
                onBlur={() => {
                    blurTimer.current = window.setTimeout(() => {
                        setOpen(false);
                        setSearching(false);
                        blurTimer.current = null;
                    }, 100);
                }}
                onKeyDown={(event) => {
                    if (event.key === "ArrowDown") {
                        event.preventDefault();
                        setOpen(true);
                        setActiveIndex((current) =>
                            Math.min(
                                current + 1,
                                Math.max(shown.length - 1, 0),
                            ),
                        );
                    }
                    if (event.key === "ArrowUp") {
                        event.preventDefault();
                        setActiveIndex((current) => Math.max(current - 1, 0));
                    }
                    if (event.key === "Enter" && open && shown[activeIndex]) {
                        event.preventDefault();
                        choose(shown[activeIndex]);
                    }
                    if (event.key === "Escape") {
                        setOpen(false);
                        setQuery("");
                        setSearching(false);
                    }
                }}
            />
            {open && (
                <ul
                    id={listId}
                    role="listbox"
                    className="absolute z-30 mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-slate-300 bg-white p-1 shadow-lg"
                >
                    {shown.map((option, index) => (
                        <li
                            key={String(option.value)}
                            id={`${listId}-${index}`}
                            role="option"
                            aria-selected={
                                String(option.value) === String(value)
                            }
                            aria-disabled={option.disabled || undefined}
                            className={`cursor-pointer rounded-md px-3 py-2 text-sm ${index === activeIndex ? "bg-emerald-50 text-emerald-950" : ""} ${option.disabled ? "cursor-not-allowed text-slate-400" : ""}`}
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => choose(option)}
                        >
                            {option.label}
                        </li>
                    ))}
                    {!shown.length && (
                        <li className="px-3 py-2 text-sm text-slate-500">
                            No matching options.
                        </li>
                    )}
                </ul>
            )}
        </div>
    );
}
