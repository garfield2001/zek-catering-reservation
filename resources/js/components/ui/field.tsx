import {
    Children,
    cloneElement,
    isValidElement,
    type ReactNode,
    type ReactElement,
} from "react";
export function Field({
    label,
    error,
    children,
    id,
}: {
    label: string;
    error?: string;
    children: ReactNode;
    id: string;
}) {
    return (
        <div className="space-y-1.5">
            <label className="block text-sm font-medium" htmlFor={id}>
                {label}
            </label>
            {Children.map(children, (child) =>
                isValidElement(child) &&
                (child as ReactElement<{ id?: string }>).props.id === id
                    ? cloneElement(
                          child as ReactElement<{
                              "aria-invalid"?: boolean;
                              "aria-describedby"?: string;
                          }>,
                          {
                              "aria-invalid": !!error,
                              "aria-describedby": error
                                  ? id + "-error"
                                  : undefined,
                          },
                      )
                    : child,
            )}
            {error && (
                <p
                    id={id + "-error"}
                    role="alert"
                    className="text-sm text-red-700"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
