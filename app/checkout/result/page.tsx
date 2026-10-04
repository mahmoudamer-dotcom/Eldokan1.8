import InvoiceResult from '@/components/checkout/InvoiceResult'

export default async function CheckoutResultPage({ searchParams }: { searchParams: Promise<{ reference?: string | string[] }> }) {
  const params = await searchParams
  const reference = Array.isArray(params.reference) ? params.reference[0] : params.reference ?? ''
  return <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-12 sm:py-16">
    <InvoiceResult reference={reference} />
  </main>
}
