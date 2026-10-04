'use client'

import { useEffect, useRef, useState } from 'react'

type Point = { lat: number; lng: number }
type MapEvent = { latLng: { lat: () => number; lng: () => number } }
type GoogleMap = {
  addListener: (event: string, callback: (event: MapEvent) => void) => { remove: () => void }
  setCenter: (point: Point) => void
  setZoom: (zoom: number) => void
}
type GoogleMarker = {
  setPosition: (point: Point) => void
  addListener: (event: string, callback: (event: MapEvent) => void) => { remove: () => void }
}
type GoogleMapsApi = {
  maps: {
    Map: new (element: HTMLElement, options: { center: Point; zoom: number; mapTypeControl: boolean; streetViewControl: boolean }) => GoogleMap
    Marker: new (options: { map: GoogleMap; position: Point; draggable: boolean; title: string }) => GoogleMarker
  }
}
declare global { interface Window { google?: GoogleMapsApi } }

type Props = { apiKey?: string; point: Point | null; onChange: (point: Point | null) => void; locale: 'ar' | 'en' }

export default function GoogleMapPicker({ apiKey, point, onChange, locale }: Props) {
  const mapElement = useRef<HTMLDivElement>(null)
  const mapRef = useRef<GoogleMap | null>(null)
  const markerRef = useRef<GoogleMarker | null>(null)
  const onChangeRef = useRef(onChange)
  const pointRef = useRef(point)
  const [mapError, setMapError] = useState('')
  const [manualLat, setManualLat] = useState('')
  const [manualLng, setManualLng] = useState('')
  const ar = locale === 'ar'

  useEffect(() => { onChangeRef.current = onChange }, [onChange])
  useEffect(() => { pointRef.current = point }, [point])

  useEffect(() => {
    setManualLat(point ? String(point.lat) : '')
    setManualLng(point ? String(point.lng) : '')
  }, [point])

  useEffect(() => {
    if (!apiKey || !mapElement.current) return
    let cancelled = false
    const initialize = () => {
      if (cancelled || !mapElement.current || !window.google) return
      const selectedPoint = pointRef.current
      const initialPoint = selectedPoint ?? { lat: 30.0444, lng: 31.2357 }
      const map = new window.google.maps.Map(mapElement.current, {
        center: initialPoint, zoom: selectedPoint ? 16 : 11, mapTypeControl: false, streetViewControl: false,
      })
      const marker = new window.google.maps.Marker({ map, position: initialPoint, draggable: true, title: ar ? 'موقع التوصيل' : 'Delivery location' })
      map.addListener('click', (event) => {
        const nextPoint = { lat: event.latLng.lat(), lng: event.latLng.lng() }
        marker.setPosition(nextPoint)
        onChangeRef.current(nextPoint)
      })
      marker.addListener('dragend', (event) => onChangeRef.current({ lat: event.latLng.lat(), lng: event.latLng.lng() }))
      mapRef.current = map
      markerRef.current = marker
      if (selectedPoint) onChangeRef.current(selectedPoint)
    }

    if (window.google?.maps) {
      initialize()
      return () => { cancelled = true }
    }
    const existing = document.querySelector<HTMLScriptElement>('script[data-eldokan-google-maps]')
    if (existing) {
      existing.addEventListener('load', initialize, { once: true })
      return () => { cancelled = true; existing.removeEventListener('load', initialize) }
    }
    const script = document.createElement('script')
    script.dataset.eldokanGoogleMaps = 'true'
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&v=weekly`
    script.async = true
    script.onload = initialize
    script.onerror = () => setMapError(ar ? 'تعذر تحميل خرائط Google. يمكنك استخدام GPS أو إدخال الإحداثيات.' : 'Google Maps could not load. You can use GPS or enter coordinates.')
    document.head.appendChild(script)
    return () => { cancelled = true; script.onload = null; script.onerror = null }
    // Map is created once; selection updates flow through refs.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [apiKey, ar])

  useEffect(() => {
    if (!point) return
    markerRef.current?.setPosition(point)
    mapRef.current?.setCenter(point)
    mapRef.current?.setZoom(16)
  }, [point])

  function useCurrentLocation() {
    if (!navigator.geolocation) {
      setMapError(ar ? 'المتصفح لا يدعم تحديد الموقع.' : 'This browser does not support location services.')
      return
    }
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        const nextPoint = { lat: coords.latitude, lng: coords.longitude }
        onChange(nextPoint)
      },
      () => setMapError(ar ? 'لم نتمكن من تحديد موقعك. اسمح للمتصفح بالوصول للموقع أو حدد النقطة على الخريطة.' : 'Could not get your location. Allow location access or select a point on the map.'),
      { enableHighAccuracy: true, timeout: 12000 },
    )
  }

  return <div className="space-y-3">
    <div className="flex flex-wrap items-center justify-between gap-3">
      <div><h3 className="font-semibold">{ar ? 'موقع التوصيل على الخريطة' : 'Delivery location on map'}</h3><p className="text-sm text-gray-600">{ar ? 'اضغط على الخريطة أو حرّك العلامة لتحديد موقعك.' : 'Click the map or drag the marker to set your location.'}</p></div>
      <button type="button" onClick={useCurrentLocation} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold hover:bg-gray-50">{ar ? 'استخدم GPS' : 'Use GPS'}</button>
    </div>
    {apiKey ? <div ref={mapElement} className="h-72 w-full rounded-xl bg-gray-100" aria-label={ar ? 'خريطة Google' : 'Google map'} /> : <div className="rounded-xl border border-dashed border-amber-400 bg-amber-50 p-4 text-sm text-amber-900">{ar ? 'أضف NEXT_PUBLIC_GOOGLE_MAPS_API_KEY لعرض الخريطة. يظل بإمكانك استخدام GPS أو إدخال الإحداثيات يدويًا.' : 'Set NEXT_PUBLIC_GOOGLE_MAPS_API_KEY to display the map. You can still use GPS or enter coordinates manually.'}</div>}
    {(point || !apiKey) && <div className="grid grid-cols-2 gap-3">
      <label className="text-sm">{ar ? 'خط العرض' : 'Latitude'}<input aria-label="Latitude" type="number" step="any" min="-90" max="90" value={manualLat} onChange={(event) => { const value = event.target.value; setManualLat(value); const lat = Number(value); const lng = Number(manualLng); if (value && manualLng && Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) onChange({ lat, lng }); else onChange(null) }} placeholder="30.0444" className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" /></label>
      <label className="text-sm">{ar ? 'خط الطول' : 'Longitude'}<input aria-label="Longitude" type="number" step="any" min="-180" max="180" value={manualLng} onChange={(event) => { const value = event.target.value; setManualLng(value); const lat = Number(manualLat); const lng = Number(value); if (value && manualLat && Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) onChange({ lat, lng }); else onChange(null) }} placeholder="31.2357" className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" /></label>
    </div>}
    {point && <p className="text-xs text-gray-600">{ar ? 'الإحداثيات المختارة' : 'Selected coordinates'}: {point.lat.toFixed(6)}, {point.lng.toFixed(6)}</p>}
    {mapError && <p role="alert" className="text-sm text-red-700">{mapError}</p>}
  </div>
}
