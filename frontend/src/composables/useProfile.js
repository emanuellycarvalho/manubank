import { ref } from 'vue'

const PHOTO_KEY = 'manubank_profile_photo'
const NAME_KEY = 'manubank_profile_name'
const MAX_BYTES = 512 * 1024

export function useProfile() {
  const profilePhoto = ref(localStorage.getItem(PHOTO_KEY) || null)
  const profileName = ref(localStorage.getItem(NAME_KEY) || '')
  const profileError = ref(null)

  function savePhoto(dataUrl) {
    profilePhoto.value = dataUrl
    profileError.value = null
    if (dataUrl) localStorage.setItem(PHOTO_KEY, dataUrl)
    else localStorage.removeItem(PHOTO_KEY)
  }

  function saveProfileName() {
    const trimmed = profileName.value.trim()
    profileName.value = trimmed
    if (trimmed) localStorage.setItem(NAME_KEY, trimmed)
    else localStorage.removeItem(NAME_KEY)
  }

  function removePhoto() {
    savePhoto(null)
  }

  function onFileChange(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    profileError.value = null
    if (!file) return
    if (!file.type.startsWith('image/')) {
      profileError.value = 'Selecione um arquivo de imagem.'
      return
    }
    if (file.size > MAX_BYTES) {
      profileError.value = 'A imagem deve ter no máximo 512 KB.'
      return
    }

    const reader = new FileReader()
    reader.onload = () => {
      if (typeof reader.result === 'string') savePhoto(reader.result)
    }
    reader.readAsDataURL(file)
  }

  return {
    profilePhoto,
    profileName,
    profileError,
    onFileChange,
    removePhoto,
    saveProfileName,
  }
}
