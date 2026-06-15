"use client";

import type { FormEvent } from "react";
import { useEffect, useMemo, useState } from "react";
import { dateOnly } from "@/lib/catering";
import { catalogLoadError } from "../domain/constants";
import { calculateInquiryTotals } from "../domain/pricing";
import type { Catalog, InquiryResult, InquirySelection } from "../domain/types";
import { validateInquirySelection } from "../domain/validation";
import { toggleSelection } from "../application/selection";
import { loadInquiryCatalog } from "../infrastructure/catalog-client";
import { submitInquiry } from "../infrastructure/submit-inquiry";
import { CatalogErrorState, CatalogLoadingState } from "./catalog-state";
import { InquiryForm } from "./inquiry-form";
import { InquirySuccessPanel } from "./inquiry-success-panel";

export function InquiryBuilder({ initialCatalog, initialCatalogError }: { initialCatalog?: Catalog | null; initialCatalogError?: string | null }) {
  const [catalog, setCatalog] = useState<Catalog | null>(initialCatalog ?? null);
  const [selectedPackageId, setSelectedPackageId] = useState(() => initialCatalog?.packages[0]?.id ?? "");
  const [pax, setPax] = useState(() => initialCatalog?.packages[0]?.minimum_pax ?? 50);
  const [selection, setSelection] = useState<InquirySelection>({ dishIds: [], drinkIds: [], addonIds: [], requirements: [] });
  const [result, setResult] = useState<InquiryResult | null>(null);
  const [copied, setCopied] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [catalogError, setCatalogError] = useState(initialCatalogError ?? "");
  const [catalogLoading, setCatalogLoading] = useState(!initialCatalog && !initialCatalogError);
  const [loading, setLoading] = useState(false);
  const [minEventDate] = useState(() => dateOnly(new Date(Date.now() + 13 * 24 * 60 * 60 * 1000)));

  useEffect(() => {
    if (initialCatalog || initialCatalogError) return;

    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 12000);

    loadInquiryCatalog(controller.signal)
      .then((loadedCatalog) => {
        setCatalog(loadedCatalog);
        setSelectedPackageId(loadedCatalog.packages[0]?.id ?? "");
        setPax(loadedCatalog.packages[0]?.minimum_pax ?? 50);
        setCatalogError("");
      })
      .catch((loadError) => {
        if (!controller.signal.aborted) setCatalogError(loadError instanceof Error ? loadError.message : catalogLoadError);
      })
      .finally(() => {
        window.clearTimeout(timeout);
        setCatalogLoading(false);
      });

    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, [initialCatalog, initialCatalogError]);

  const selectedPackage = useMemo(() => catalog?.packages.find((item) => item.id === selectedPackageId), [catalog, selectedPackageId]);
  const selectedDishes = useMemo(() => catalog?.dishes.filter((item) => selection.dishIds.includes(item.id)) ?? [], [catalog, selection.dishIds]);
  const selectedDrinks = useMemo(() => catalog?.drinks.filter((item) => selection.drinkIds.includes(item.id)) ?? [], [catalog, selection.drinkIds]);
  const selectedAddons = useMemo(() => catalog?.addons.filter((item) => selection.addonIds.includes(item.id)) ?? [], [catalog, selection.addonIds]);
  const totals = useMemo(
    () => calculateInquiryTotals({ packageRule: selectedPackage, pax, selectedDishes, selectedDrinks, selectedAddons }),
    [selectedPackage, pax, selectedDishes, selectedDrinks, selectedAddons],
  );

  function updatePackage(packageId: string) {
    const nextPackage = catalog?.packages.find((item) => item.id === packageId);
    setSelectedPackageId(packageId);
    setPax(nextPackage?.minimum_pax ?? 50);
    setSelection((current) => ({
      ...current,
      dishIds: current.dishIds.slice(0, nextPackage?.meal_slots ?? 0),
      drinkIds: current.drinkIds.slice(0, nextPackage?.drink_slots ?? 0),
    }));
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);

    const validationError = validateInquirySelection({
      packageRule: selectedPackage,
      pax,
      dishCount: selection.dishIds.length,
      drinkCount: selection.drinkIds.length,
    });
    if (validationError || !selectedPackage) {
      setError(validationError ?? "Select a package first.");
      return;
    }

    setLoading(true);
    try {
      const createdInquiry = await submitInquiry({ form: event.currentTarget, packageRule: selectedPackage, pax, selection });
      setResult(createdInquiry);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : "Submission failed. Please try again.");
    } finally {
      setLoading(false);
    }
  }

  if (result) {
    return (
      <InquirySuccessPanel
        result={result}
        copied={copied}
        onCopy={async () => {
          await navigator.clipboard.writeText(result.refCode);
          setCopied(true);
        }}
        onNewInquiry={() => setResult(null)}
      />
    );
  }

  if (catalogLoading) return <CatalogLoadingState />;
  if (catalogError || !catalog) return <CatalogErrorState error={catalogError || catalogLoadError} />;

  return (
    <InquiryForm
      catalog={catalog}
      selectedPackage={selectedPackage}
      selectedPackageId={selectedPackageId}
      pax={pax}
      minEventDate={minEventDate}
      selection={selection}
      selectedAddons={selectedAddons}
      totals={totals}
      loading={loading}
      error={error}
      onPackageChange={updatePackage}
      onPaxChange={setPax}
      onToggleDish={(dishId) => setSelection((current) => ({ ...current, dishIds: toggleSelection(current.dishIds, dishId, selectedPackage?.meal_slots) }))}
      onToggleDrink={(drinkId) => setSelection((current) => ({ ...current, drinkIds: toggleSelection(current.drinkIds, drinkId, selectedPackage?.drink_slots) }))}
      onToggleAddon={(addonId) => setSelection((current) => ({ ...current, addonIds: toggleSelection(current.addonIds, addonId) }))}
      onToggleRequirement={(requirement) => setSelection((current) => ({ ...current, requirements: toggleSelection(current.requirements, requirement) }))}
      onSubmit={handleSubmit}
    />
  );
}
