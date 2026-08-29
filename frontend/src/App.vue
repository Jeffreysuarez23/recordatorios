<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from './api'

// --- Autenticación ---
const isAuthenticated = ref(!!localStorage.getItem('auth_token'))
const user = ref(JSON.parse(localStorage.getItem('user_data') || '{}'))

const loginForm = ref({ email: '', password: '' })
const loginError = ref('')
const isLoggingIn = ref(false)


const isRegisterView = ref(false)
const registerForm = ref({ nombre: '', email: '', password: '' })
const registerError = ref('')
const isRegistering = ref(false)

const handleRegister = async () => {
  registerError.value = ''
  isRegistering.value = true
  try {
    const res = await api.post('/auth/register', registerForm.value)
    if (res.data.success) {
      localStorage.setItem('auth_token', res.data.data.token)
      localStorage.setItem('user_data', JSON.stringify(res.data.data.user))
      user.value = res.data.data.user
      isAuthenticated.value = true
      await loadInitialData()
    }
  } catch (error) {
    registerError.value = error.response?.data?.message || 'Error al registrar usuario'
  } finally {
    isRegistering.value = false
  }
}

const handleLogin = async () => {
  loginError.value = ''
  isLoggingIn.value = true
  try {
    const res = await api.post('/auth/login', loginForm.value)
    if (res.data.success) {
      localStorage.setItem('auth_token', res.data.data.token)
      localStorage.setItem('user_data', JSON.stringify(res.data.data.user))
      user.value = res.data.data.user
      isAuthenticated.value = true
      await loadInitialData()
    }
  } catch (error) {
    loginError.value = error.response?.data?.message || 'Error al iniciar sesión'
  } finally {
    isLoggingIn.value = false
  }
}


const isDarkMode = ref(localStorage.getItem('theme') === 'dark')

watch(isDarkMode, (val) => {
  if (val) {
    document.body.classList.add('dark-mode')
    localStorage.setItem('theme', 'dark')
  } else {
    document.body.classList.remove('dark-mode')
    localStorage.setItem('theme', 'light')
  }
}, { immediate: true })

const setTheme = (mode) => {
  isDarkMode.value = (mode === 'dark')
}

const handleLogout = async () => {
  try {
    await api.post('/auth/logout')
  } catch (e) {
    console.error(e)
  }
  localStorage.removeItem('auth_token')
  localStorage.removeItem('user_data')
  isAuthenticated.value = false
}


// --- Datos principales ---
const categories = ref([])
const activeFilter = ref('todas')
const upcomingReminders = ref([])
const filteredUpcomingReminders = computed(() => {
  if (activeFilter.value === 'todas') return upcomingReminders.value
  return upcomingReminders.value.filter(evt => evt.category.nombre.toLowerCase() === activeFilter.value.toLowerCase())
})

const upcomingPage = ref(1)
const upcomingPerPage = 4

watch(activeFilter, () => {
  upcomingPage.value = 1
})

const paginatedUpcomingReminders = computed(() => {
  const start = (upcomingPage.value - 1) * upcomingPerPage
  return filteredUpcomingReminders.value.slice(start, start + upcomingPerPage)
})

const totalUpcomingPages = computed(() => {
  return Math.ceil(filteredUpcomingReminders.value.length / upcomingPerPage) || 1
})

const calendarData = ref({})

// Estado para el modal de recordatorio (al dar click en un día)
const showModal = ref(false)
const selectedDayEvents = ref([])
const currentEventIndex = ref(0)
const currentEvent = computed(() => selectedDayEvents.value[currentEventIndex.value] || {})

const openModal = (events) => {
  if (!events || events.length === 0) return
  selectedDayEvents.value = events
  currentEventIndex.value = 0
  showModal.value = true
}
const closeModal = () => {
  showModal.value = false
}


const showDeleteConfirm = ref(false)
const eventToDelete = ref(null)
const isDeleting = ref(false)

const confirmDelete = (id) => {
  eventToDelete.value = id
  showDeleteConfirm.value = true
}

const cancelDelete = () => {
  showDeleteConfirm.value = false
  eventToDelete.value = null
}

const executeDelete = async () => {
  if (!eventToDelete.value) return
  isDeleting.value = true
  try {
    await api.delete(`/reminders/${eventToDelete.value}`)
    showDeleteConfirm.value = false
    closeModal()
    await loadInitialData()
  } catch (e) {
    console.error(e)
    alert('Error al eliminar el recordatorio')
  } finally {
    isDeleting.value = false
    eventToDelete.value = null
  }
}

const markAsDone = async (id) => {
  try {
    await api.patch(`/reminders/${id}/estado`, { estado: 'completado' })
    closeModal()
    await loadInitialData()
  } catch (e) {
    console.error(e)
    alert('Error al marcar como completado')
  }
}

const currentYear = ref(new Date().getFullYear())
const currentMonth = ref(new Date().getMonth() + 1) // 1-12
const monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']

// Computed calendar days (Lunes a Domingo)
const calendarDays = computed(() => {
  const daysInMonth = new Date(currentYear.value, currentMonth.value, 0).getDate()
  // Day of week of the 1st day (0 = Dom, 1 = Lun ... 6 = Sab)
  let firstDay = new Date(currentYear.value, currentMonth.value - 1, 1).getDay()
  // Ajustar para que la semana empiece en Lunes (0 = Lun ... 6 = Dom)
  const startOffset = firstDay === 0 ? 6 : firstDay - 1

  const days = []
  for (let i = 0; i < startOffset; i++) {
    days.push({ type: 'muted', day: null, events: [] })
  }
  
  const today = new Date()
  const isCurrentMonthYear = today.getFullYear() === currentYear.value && (today.getMonth() + 1) === currentMonth.value
  const todayDay = today.getDate()

  for (let d = 1; d <= daysInMonth; d++) {
    const isToday = isCurrentMonthYear && d === todayDay
    
    // Obtener eventos para este día desde el backend (agrupados por día)
    let dayEvents = []
    if (calendarData.value[d]) {
       // Filtramos según categoría activa
       dayEvents = calendarData.value[d].filter(evt => {
          if (activeFilter.value === 'todas') return true
          return evt.category.nombre.toLowerCase() === activeFilter.value.toLowerCase()
       })
    }

    days.push({
      type: isToday ? 'today' : 'normal',
      day: d,
      events: dayEvents
    })
  }
  return days
})

const setFilter = (filterName) => {
  activeFilter.value = filterName
}

// --- Carga de datos de la API ---
const loadCalendarData = async () => {
  try {
    const calRes = await api.get(`/reminders/calendario/${currentYear.value}/${currentMonth.value}`)
    calendarData.value = calRes.data.data
  } catch (error) {
    if (error.response?.status === 401) handleLogout()
    console.error('Error cargando calendario:', error)
  }
}

const loadInitialData = async () => {
  if (!isAuthenticated.value) return
  try {
    const [catRes, calRes, proxRes] = await Promise.all([
      api.get('/categories'),
      api.get(`/reminders/calendario/${currentYear.value}/${currentMonth.value}`),
      api.get('/reminders/proximos')
    ])

    categories.value = catRes.data.data
    calendarData.value = calRes.data.data
    upcomingReminders.value = proxRes.data.data

  } catch (error) {
    if (error.response?.status === 401) {
       handleLogout()
    }
    console.error('Error cargando datos:', error)
  }
}

// Navegación de mes
const prevMonth = async () => {
  if (currentMonth.value === 1) {
    currentMonth.value = 12
    currentYear.value--
  } else {
    currentMonth.value--
  }
  await loadCalendarData()
}
const nextMonth = async () => {
  if (currentMonth.value === 12) {
    currentMonth.value = 1
    currentYear.value++
  } else {
    currentMonth.value++
  }
  await loadCalendarData()
}
const goToday = async () => {
  const d = new Date()
  currentYear.value = d.getFullYear()
  currentMonth.value = d.getMonth() + 1
  await loadCalendarData()
}


onMounted(() => {
  // Ajustar mes inicial a la fecha actual si queremos, o dejar Marzo 2026 para testing
  // const d = new Date()
  // currentYear.value = d.getFullYear()
  // currentMonth.value = d.getMonth() + 1
  if (isAuthenticated.value) {
    loadInitialData()
  }
})


// --- Crear Recordatorio ---

const showErrorModal = ref(false)
const errorMessage = ref('')

const showCreateModal = ref(false)
const isCreating = ref(false)
const createForm = ref({
  titulo: '',
  category_id: '',
  fecha: '',
  hora: '',
  lugar: '',
  descripcion: ''
})

watch(() => createForm.value.titulo, (newVal) => {
  if (newVal && newVal.length > 0) {
    createForm.value.titulo = newVal.charAt(0).toUpperCase() + newVal.slice(1);
  }
})
watch(() => createForm.value.lugar, (newVal) => {
  if (newVal && newVal.length > 0) {
    createForm.value.lugar = newVal.charAt(0).toUpperCase() + newVal.slice(1);
  }
})
watch(() => createForm.value.descripcion, (newVal) => {
  if (newVal && newVal.length > 0) {
    createForm.value.descripcion = newVal.charAt(0).toUpperCase() + newVal.slice(1);
  }
})


const openCreateModal = () => {
  createForm.value = { titulo: '', category_id: '', fecha: '', hora: '', lugar: '', descripcion: '' }
  showCreateModal.value = true
}

const closeCreateModal = () => {
  showCreateModal.value = false
}

const handleCreateReminder = async () => {
  isCreating.value = true
  try {
    const payload = { ...createForm.value }
    if (!payload.hora) delete payload.hora
    if (!payload.lugar) delete payload.lugar
    if (!payload.descripcion) delete payload.descripcion
    
    await api.post('/reminders', payload)
    closeCreateModal()
    await loadInitialData() // Reload calendar and upcoming
  } catch (error) {
    console.error('Error al crear recordatorio', error)
    if (error.response?.data?.errors?.fecha) {
      errorMessage.value = 'No puedes crear un recordatorio en una fecha pasada. Por favor, selecciona hoy o una fecha futura.'
    } else {
      errorMessage.value = error.response?.data?.message || 'Ocurrió un error al intentar guardar el recordatorio.'
    }
    showErrorModal.value = true
  } finally {
    isCreating.value = false
  }
}

// Funciones útiles de formato

const getDayNumber = (dateStr) => {
  if(!dateStr) return ''
  const datePart = dateStr.split('T')[0]
  return datePart.split('-')[2]
}
const getMonthShort = (dateStr) => {
  if(!dateStr) return ''
  const datePart = dateStr.split('T')[0]
  const m = parseInt(datePart.split('-')[1])
  return monthNames[m].substring(0,3)
}
const formatDateTime = (fecha, hora) => {
  if (!fecha) return ''
  const datePart = fecha.split('T')[0]
  let f = new Date(datePart + 'T00:00:00').toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  if (hora) {
    const h = hora.substring(0,5)
    f += ` · ${h}`
  }
  return f
}

</script>

<template>
  <!-- Pantalla de Login -->
  <div v-if="!isAuthenticated" class="login-wrapper">
    <!-- Login Box -->
    <div v-if="!isRegisterView" class="login-box card">
      <div class="brand" style="justify-content: center; margin-bottom: 30px;">
        <div class="brand-mark">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.73 21a2 2 0 01-3.46 0" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h1 class="display" style="font-size: 26px;">Recordatorios</h1>
      </div>
      
      <p style="text-align: center; color: var(--ink-soft); margin-bottom: 24px; font-size:14px;">Ingresa a tu cuenta para continuar</p>
      
      <div v-if="loginError" class="login-error">{{ loginError }}</div>
      
      <form @submit.prevent="handleLogin" class="login-form">
        <label>Email</label>
        <input type="email" v-model="loginForm.email" required class="input-field" placeholder="ejemplo@correo.com" />
        
        <label style="margin-top: 14px;">Contraseña</label>
        <input type="password" v-model="loginForm.password" required class="input-field" placeholder="••••••••" />
        
        <button type="submit" class="btn btn-primary" style="margin-top: 24px; width: 100%;" :disabled="isLoggingIn">
          {{ isLoggingIn ? 'Iniciando...' : 'Iniciar Sesión' }}
        </button>
        <div style="text-align:center; margin-top:16px; font-size: 13px;">
          <span style="color:var(--ink-soft);">¿No tienes cuenta?</span> 
          <a href="#" @click.prevent="isRegisterView = true" style="color:var(--medico); font-weight:600; text-decoration:none; margin-left:4px;">Regístrate</a>
        </div>
      </form>
    </div>

    <!-- Register Box -->
    <div v-if="isRegisterView" class="login-box card">
      <div class="brand" style="justify-content: center; margin-bottom: 30px;">
        <div class="brand-mark">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.73 21a2 2 0 01-3.46 0" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h1 class="display" style="font-size: 26px;">Recordatorios</h1>
      </div>
      
      <p style="text-align: center; color: var(--ink-soft); margin-bottom: 24px; font-size:14px;">Crea una cuenta nueva</p>
      
      <div v-if="registerError" class="login-error">{{ registerError }}</div>
      
      <form @submit.prevent="handleRegister" class="login-form">
        <label>Nombre</label>
        <input type="text" v-model="registerForm.nombre" required class="input-field" placeholder="Tu nombre" />

        <label style="margin-top: 14px;">Email</label>
        <input type="email" v-model="registerForm.email" required class="input-field" placeholder="ejemplo@correo.com" />
        
        <label style="margin-top: 14px;">Contraseña</label>
        <input type="password" v-model="registerForm.password" required minlength="6" class="input-field" placeholder="Mínimo 6 caracteres" />
        
        <button type="submit" class="btn btn-primary" style="margin-top: 24px; width: 100%;" :disabled="isRegistering">
          {{ isRegistering ? 'Registrando...' : 'Crear Cuenta' }}
        </button>
        <div style="text-align:center; margin-top:16px; font-size: 13px;">
          <span style="color:var(--ink-soft);">¿Ya tienes cuenta?</span> 
          <a href="#" @click.prevent="isRegisterView = false" style="color:var(--medico); font-weight:600; text-decoration:none; margin-left:4px;">Inicia Sesión</a>
        </div>
      </form>
    </div>

  </div>

  <!-- Aplicación Principal -->
  <div v-else class="app">

    <header class="top">
      <div class="brand">
        <div class="brand-mark">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.73 21a2 2 0 01-3.46 0" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div>
          <h1 class="display">Recordatorios</h1>
          <span>Todo lo importante, en un solo lugar</span>
        </div>
      </div>
      <div class="user-dropdown-container">
        <div class="user-chip" style="cursor: default;">
          <div class="avatar">{{ user.nombre ? user.nombre.substring(0,2).toUpperCase() : 'U' }}</div>
          <span>{{ user.nombre }}</span>
        </div>
        <div class="user-dropdown-menu">
          <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid var(--line); margin-bottom: 4px;">
            <span style="font-size: 12.5px; font-weight: 500; color: var(--ink-soft);">Tema</span>
            <div style="display: flex; gap: 8px;">
              <button @click="setTheme('light')" style="width: 20px; height: 20px; border-radius: 50%; border: 1px solid #DCE4DF; background: #FFFFFF; cursor: pointer; transition: all .2s;" :style="!isDarkMode ? 'box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--medico);' : ''" title="Modo Claro"></button>
              <button @click="setTheme('dark')" style="width: 20px; height: 20px; border-radius: 50%; border: 1px solid #333333; background: #121212; cursor: pointer; transition: all .2s;" :style="isDarkMode ? 'box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--medico);' : ''" title="Modo Oscuro"></button>
            </div>
          </div>
          <button @click="handleLogout" class="dropdown-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M17 16l4-4m0 0l-4-4m4 4H9m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h6a3 3 0 013 3v1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Cerrar sesión
          </button>
        </div>
      </div>
    </header>

    <svg class="vital-line top-divider" viewBox="0 0 1200 20" preserveAspectRatio="none">
      <line class="track" x1="0" y1="10" x2="1200" y2="10"/>
      <circle class="node" cx="120" cy="10" r="3"/>
      <circle class="node" cx="360" cy="10" r="3"/>
      <circle class="node" cx="600" cy="10" r="3"/>
      <circle class="node" cx="840" cy="10" r="3"/>
      <circle class="node" cx="1080" cy="10" r="3"/>
      <line class="tick" x1="120" y1="4" x2="120" y2="16"/>
      <line class="tick" x1="600" y1="4" x2="600" y2="16"/>
      <line class="tick" x1="1080" y1="4" x2="1080" y2="16"/>
    </svg>

    <div class="layout">
      
      <div class="main-content">
        <div class="filters">
          <span class="filters-label">Filtrar</span>
          <div class="chip" :class="{ active: activeFilter === 'todas' }" @click="setFilter('todas')">
            <span class="dot" style="background:var(--ink)"></span>Todos
          </div>
          <div v-for="cat in categories" :key="cat.id" class="chip" :class="{ active: activeFilter === cat.nombre }" @click="setFilter(cat.nombre)">
            <span class="dot" :style="{ background: cat.color }"></span>{{ cat.nombre }}
          </div>
        </div>

        <!-- CALENDAR -->
      <div class="card">
        <div class="cal-head">
          <div class="cal-title-group">
            <span class="cal-title display">{{ monthNames[currentMonth] }}</span>
            <span class="cal-year">{{ currentYear }}</span>
          </div>
          <div class="cal-nav">
            <button class="nav-today" @click="goToday">Hoy</button>
            <button class="nav-btn" @click="prevMonth">
              <svg viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button class="nav-btn" @click="nextMonth">
              <svg viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </div>
        </div>

        <div class="cal-grid">
          <div class="cal-weekdays">
            <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
          </div>
          <div class="cal-days">
            <div v-for="(day, index) in calendarDays" :key="index" class="day-cell" :class="[day.type, { 'today': day.type === 'today', 'muted': day.type === 'muted' }]" @click="openModal(day.events)">
              <span v-if="day.day !== null" class="day-num">{{ day.day }}</span>
              <div v-if="day.day !== null" class="day-dots">
                <span v-for="evt in day.events" :key="evt.id" class="dot" :style="{ background: evt.category.color }" :title="evt.titulo"></span>
              </div>
            </div>
          </div>
        </div>
      </div>
      </div>

      <!-- SIDEBAR -->
      <div class="sidebar">
        <div class="sidebar-head">
          <h2>Próximos recordatorios</h2>
          <span>{{ filteredUpcomingReminders.length }} pendientes</span>
        </div>

        <div v-if="filteredUpcomingReminders.length === 0" style="padding: 20px; text-align: center; color: var(--ink-soft); font-size: 13px;">
          No hay recordatorios próximos.
        </div>

        <div v-for="appt in paginatedUpcomingReminders" :key="appt.id" class="appt-card" @click="openModal([appt])" :style="{ '--local-color': appt.category.color, '--local-bg': appt.category.color + '22' }">
          <div class="appt-date" :style="{ backgroundColor: 'var(--local-bg)', color: 'var(--local-color)' }">
            <span class="d">{{ getDayNumber(appt.fecha) }}</span>
            <span class="m">{{ getMonthShort(appt.fecha) }}</span>
          </div>
          <div class="appt-body">
            <div class="appt-top">
              <span class="appt-title">{{ appt.titulo }}</span>
              <span class="appt-time">{{ appt.hora ? appt.hora.substring(0,5) : 'Todo el día' }}</span>
            </div>
            <div class="appt-meta">
              <span class="type-tag" :style="{ backgroundColor: 'var(--local-bg)', color: 'var(--local-color)' }">{{ appt.category.nombre }}</span>
              <span v-if="appt.lugar">{{ appt.lugar }}</span>
              
              <span v-if="appt.es_recurrente" class="recurring-tag" style="margin-left: auto;">
                <svg viewBox="0 0 24 24" fill="none"><path d="M17 2l4 4-4 4M7 22l-4-4 4-4M3 6h13a4 4 0 014 4M21 18H8a4 4 0 01-4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ appt.frecuencia_recurrencia }}
              </span>
            </div>
          </div>
        </div>

        <div v-if="totalUpcomingPages > 1" class="sidebar-pagination">
          <button class="page-btn" :disabled="upcomingPage === 1" @click="upcomingPage--">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          
          <div class="page-numbers">
             <button v-for="p in totalUpcomingPages" :key="p" class="page-num" :class="{ active: p === upcomingPage }" @click="upcomingPage = p">{{ p }}</button>
          </div>
          
          <button class="page-btn" :disabled="upcomingPage === totalUpcomingPages" @click="upcomingPage++">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>

      </div>
    </div>
  </div>


  <!-- FAB -->
  <button v-if="isAuthenticated" class="fab" @click="openCreateModal">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="white" stroke-width="2.4" stroke-linecap="round"/></svg>
    Nuevo recordatorio
  </button>

  <!-- MODAL CREAR -->
  <div v-if="isAuthenticated" class="overlay" :class="{ show: showCreateModal }" @click.self="closeCreateModal">
    <div class="modal">
      <div class="modal-head" style="padding-bottom: 0;">
        <div class="modal-title display">Nuevo Recordatorio</div>
        <div class="modal-close" @click="closeCreateModal">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
      </div>
      <form @submit.prevent="handleCreateReminder">
        <div class="modal-body" style="padding-top: 16px;">
          <label style="font-size:12px; font-weight:600; margin-bottom:4px; display:block;">Título</label>
          <input type="text" v-model="createForm.titulo" required class="input-field" style="width:100%; margin-bottom:12px;" placeholder="Ej. Cita médica" />

          <label style="font-size:12px; font-weight:600; margin-bottom:4px; display:block;">Categoría</label>
          <select v-model="createForm.category_id" required class="input-field" style="width:100%; margin-bottom:12px;">
            <option value="" disabled>Selecciona una categoría</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
          </select>

          <div style="display:flex; gap:12px; margin-bottom:12px;">
            <div style="flex:1;">
              <label style="font-size:12px; font-weight:600; margin-bottom:4px; display:block;">Fecha</label>
              <input type="date" v-model="createForm.fecha" required class="input-field" style="width:100%;" />
            </div>
            <div style="flex:1;">
              <label style="font-size:12px; font-weight:600; margin-bottom:4px; display:block;">Hora (opcional)</label>
              <input type="time" v-model="createForm.hora" class="input-field" style="width:100%;" />
            </div>
          </div>

          <label style="font-size:12px; font-weight:600; margin-bottom:4px; display:block;">Lugar (opcional)</label>
          <input type="text" v-model="createForm.lugar" class="input-field" style="width:100%; margin-bottom:12px;" placeholder="Ubicación o enlace" />

          <label style="font-size:12px; font-weight:600; margin-bottom:4px; display:block;">Notas (opcional)</label>
          <textarea v-model="createForm.descripcion" class="input-field" style="width:100%; resize:vertical; min-height:60px;" placeholder="Detalles adicionales"></textarea>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-ghost" @click="closeCreateModal">Cancelar</button>
          <button type="submit" class="btn btn-primary" :disabled="isCreating">{{ isCreating ? 'Guardando...' : 'Guardar' }}</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL DETALLE (Existente) -->

  <div v-if="isAuthenticated" class="overlay" :class="{ show: showModal }" @click.self="closeModal">
    <div class="modal">
      <div v-if="selectedDayEvents.length > 0">
        <div class="modal-head">
          <div style="flex:1;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
              <span class="type-tag" :style="{ backgroundColor: currentEvent.category.color + '22', color: currentEvent.category.color }">
                {{ currentEvent.category.nombre }}
              </span>
              <div v-if="selectedDayEvents.length > 1" style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--ink-soft);">
                <button type="button" @click.stop="currentEventIndex--" :disabled="currentEventIndex === 0" style="background:none;border:none;cursor:pointer;padding:4px;color:currentColor;">&lt;</button>
                {{ currentEventIndex + 1 }} de {{ selectedDayEvents.length }}
                <button type="button" @click.stop="currentEventIndex++" :disabled="currentEventIndex === selectedDayEvents.length - 1" style="background:none;border:none;cursor:pointer;padding:4px;color:currentColor;">&gt;</button>
              </div>
            </div>
            <div class="modal-title display" style="word-break: break-word;">{{ currentEvent.titulo }}</div>
          </div>
          <div class="modal-close" @click="closeModal" style="margin-left:12px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </div>
        </div>
        <div class="modal-body">
          <div class="info-row">
            <div class="icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="currentColor" stroke-width="1.8"/></svg></div>
            <div><div class="label">Fecha y hora</div><div class="value">{{ formatDateTime(currentEvent.fecha, currentEvent.hora) }}</div></div>
          </div>
          <div class="info-row" v-if="currentEvent.lugar">
            <div class="icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 21s-7-5.6-7-11a7 7 0 0114 0c0 5.4-7 11-7 11z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="2.4" stroke="currentColor" stroke-width="1.8"/></svg></div>
            <div><div class="label">Lugar</div><div class="value">{{ currentEvent.lugar }}</div></div>
          </div>
          <div class="info-row" v-if="currentEvent.descripcion">
            <div class="icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 4h16v16H4z" stroke="currentColor" stroke-width="1.8"/><path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></div>
            <div><div class="label">Notas</div><div class="value" style="white-space: pre-wrap;">{{ currentEvent.descripcion }}</div></div>
          </div>
        </div>
        <div class="modal-actions" style="justify-content: space-between;">
          <button class="btn btn-ghost" @click="confirmDelete(currentEvent.id)" style="color: #D33D3D; flex: 0 0 auto; padding: 11px 16px;" title="Eliminar recordatorio">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <div style="display:flex; gap: 10px; flex: 1;">
            <button class="btn btn-ghost" @click="closeModal">Cerrar</button>
            <button class="btn btn-primary" @click="markAsDone(currentEvent.id)">Marcar como listo</button>
          </div>
        </div>
      </div>
      <div v-else class="modal-body" style="padding-top:24px; text-align:center;">
        <p style="color:var(--ink-soft); font-size:14px;">No hay eventos en este día.</p>
        <button class="btn btn-ghost" @click="closeModal" style="margin-top:10px;">Cerrar</button>
      </div>
    </div>
  </div>

  <!-- MODAL ELIMINAR CONFIRMACION -->
  <div v-if="isAuthenticated" class="overlay" :class="{ show: showDeleteConfirm }" @click.self="cancelDelete" style="z-index: 60;">
    <div class="modal" style="width: 360px;">
      <div class="modal-body" style="padding: 24px; text-align: center;">
        <div style="width: 48px; height: 48px; border-radius: 50%; background: #FDECEC; color: #D33D3D; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h3 class="display" style="font-size: 19px; font-weight: 600; margin: 0 0 8px;">¿Eliminar recordatorio?</h3>
        <p style="font-size: 13.5px; color: var(--ink-soft); margin: 0 0 24px; line-height: 1.5;">Esta acción no se puede deshacer. El recordatorio desaparecerá de tu calendario de forma permanente.</p>
        <div style="display: flex; gap: 10px;">
          <button class="btn btn-ghost" @click="cancelDelete">Cancelar</button>
          <button class="btn" style="background: #D33D3D; color: white;" @click="executeDelete" :disabled="isDeleting">
            {{ isDeleting ? 'Eliminando...' : 'Sí, eliminar' }}
          </button>
        </div>
      </div>
    </div>
  </div>


  <!-- MODAL DE ERROR -->
  <div v-if="isAuthenticated" class="overlay" :class="{ show: showErrorModal }" @click.self="showErrorModal = false" style="z-index: 60;">
    <div class="modal" style="width: 360px;">
      <div class="modal-body" style="padding: 24px; text-align: center;">
        <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--trabajo-bg); color: var(--trabajo); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h3 class="display" style="font-size: 19px; font-weight: 600; margin: 0 0 8px;">No se pudo guardar</h3>
        <p style="font-size: 13.5px; color: var(--ink-soft); margin: 0 0 24px; line-height: 1.5;">{{ errorMessage }}</p>
        <button class="btn btn-primary" style="width: 100%;" @click="showErrorModal = false">Entendido</button>
      </div>
    </div>
  </div>

</template>

<style>
/* Aquí conservamos los estilos globales que ya tenías */
:root{
  --bg: #F2F4F1;
  --surface: #FFFFFF;
  --surface-soft: #EAF0EC;
  --ink: #1F2B27;
  --ink-soft: #5C6D66;
  --line: #DCE4DF;

  --medico: #3D6B66;      --medico-bg: #E3EEEC;
  --personal: #8B7BA8;    --personal-bg: #ECE8F3;
  --trabajo: #C98A3E;     --trabajo-bg: #F5E9D6;
  --tramites: #4C7093;    --tramites-bg: #E2EAF0;
  --pagos: #B15A4A;       --pagos-bg: #F2E1DD;
  --estudio: #78873F;     --estudio-bg: #E8EDDD;
  --otro: #6E7B8B;        --otro-bg: #E7ECF0;

  --radius-lg: 20px;
  --radius-md: 14px;
  --radius-sm: 9px;
  --shadow: 0 1px 2px rgba(31,43,39,0.04), 0 8px 24px -12px rgba(31,43,39,0.12);
}


body.dark-mode {
  color-scheme: dark;
  --bg: #121212;
  --surface: #1E1E1E;
  --surface-soft: #2C2C2C;
  --ink: #FFFFFF;
  --ink-soft: #A0A0A0;
  --line: #333333;
  --medico-bg: rgba(0, 230, 118, 0.15);
  --personal-bg: rgba(213, 0, 249, 0.15);
  --trabajo-bg: rgba(255, 145, 0, 0.15);
  --tramites-bg: rgba(41, 121, 255, 0.15);
  --pagos-bg: rgba(255, 23, 68, 0.15);
  --estudio-bg: rgba(174, 234, 0, 0.15);
  --otro-bg: rgba(245, 0, 87, 0.15);
}

*{ box-sizing: border-box; }
html,body{ margin:0; padding:0; }
body{
  background: var(--bg);
  color: var(--ink);
  font-family: 'Inter', sans-serif;
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
}

.display{ font-family: 'Outfit', sans-serif; letter-spacing: -0.02em; }
.mono{ font-family: 'Inter', sans-serif; font-variant-numeric: tabular-nums; letter-spacing: -0.01em; }

/* Timeline */
.vital-line{ width: 100%; height: 20px; display: block; }
.vital-line .track{ stroke: var(--line); stroke-width: 1.4; }
.vital-line .tick{ stroke: var(--medico); stroke-width: 2; stroke-linecap: round; opacity: 0.6; }
.vital-line .node{ fill: var(--medico); opacity: 0.75; }

.app{ max-width: 1280px; margin: 0 auto; padding: 28px 32px 60px; }
header.top{ display:flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.brand{ display:flex; align-items:center; gap: 12px; }
.brand-mark{
  width: 40px; height: 40px; border-radius: 11px;
  background: var(--medico);
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.brand-mark svg{ width:19px; height:19px; }
.brand h1{ font-size: 22px; font-weight: 600; margin: 0; letter-spacing: -0.01em; }
.brand span{ display:block; font-size: 12.5px; color: var(--ink-soft); font-family: 'Inter', sans-serif; margin-top: 1px; }

.user-chip{
  display:flex; align-items:center; gap:10px;
  background: var(--surface); border: 1px solid var(--line);
  padding: 6px 14px 6px 6px; border-radius: 999px; box-shadow: var(--shadow);
}
.avatar{
  width: 30px; height:30px; border-radius:50%;
  background: linear-gradient(135deg, var(--medico), #6FA39C);
  display:flex; align-items:center; justify-content:center;
  color:white; font-size:12.5px; font-weight:600; font-family:'Inter', sans-serif;
}
.user-chip span{ font-size: 13.5px; font-weight: 500; }

/* User Dropdown */
.user-dropdown-container { position: relative; display: inline-block; }
.user-dropdown-menu {
  position: absolute; top: 100%; right: 0; margin-top: 6px;
  background: var(--surface); border: 1px solid var(--line); border-radius: 12px;
  box-shadow: 0 10px 25px -5px rgba(31,43,39,0.15); padding: 6px; min-width: 170px;
  opacity: 0; visibility: hidden; transform: translateY(-8px);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); z-index: 100;
}
.user-dropdown-container:hover .user-dropdown-menu {
  opacity: 1; visibility: visible; transform: translateY(0);
}
.dropdown-item {
  display: flex; align-items: center; gap: 10px; width: 100%;
  padding: 10px 12px; background: transparent; border: none; border-radius: 8px;
  cursor: pointer; font-family: 'Inter', sans-serif; font-size: 13.5px;
  font-weight: 500; color: var(--ink); transition: all 0.15s ease; text-align: left;
}
.dropdown-item:hover { background: var(--pagos-bg); color: var(--pagos); }

.vital-line.top-divider{ margin: 18px 0 20px; }

.filters{ display:flex; align-items:center; gap: 9px; flex-wrap: wrap; margin-bottom: 20px; }
.filters-label{ font-size: 12.5px; color: var(--ink-soft); margin-right: 4px; text-transform: uppercase; letter-spacing: 0.06em; }
.chip{
  display:inline-flex; align-items:center; gap: 7px;
  padding: 7px 13px; border-radius: 999px; border: 1px solid var(--line);
  background: var(--surface); font-size: 13px; font-weight: 500; color: var(--ink-soft);
  cursor: pointer; transition: all .15s ease;
}
.chip .dot{ width:7px; height:7px; border-radius:50%; }
.chip.active{ background: var(--ink); color: var(--bg); border-color: var(--ink); }
.chip.active .dot{ background: var(--bg) !important; }

.layout{ display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }
.card{ background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--line); box-shadow: var(--shadow); }

.cal-head{ display: grid; grid-template-columns: repeat(7, 1fr); max-width: 480px; margin: 0 auto; padding: 16px 18px 12px; }
.cal-title-group{ grid-column: 1 / 2; display:flex; align-items:baseline; justify-content: center; gap:8px; white-space: nowrap; }
.cal-title{ font-size: 19px; font-weight:600; letter-spacing:-0.01em; text-transform: capitalize; }
.cal-year{ font-size: 12.5px; color: var(--ink-soft); font-family: 'Inter', sans-serif; font-variant-numeric: tabular-nums; font-weight: 500; }

.cal-nav{ grid-column: 6 / 8; display:flex; align-items:center; justify-content: center; gap: 6px; }
.nav-btn{
  width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--line);
  background: var(--surface); display:flex; align-items:center; justify-content:center;
  cursor:pointer; color: var(--ink); transition: background .15s ease;
}
.nav-btn svg{ width:13px; height:13px; }
.nav-btn:hover{ background: var(--surface-soft); }
.nav-today{
  font-size: 11.5px; font-weight: 600; padding: 6px 12px; border-radius: 999px;
  background: var(--surface-soft); color: var(--medico); border: none; cursor: pointer;
}

.cal-grid{ max-width: 480px; margin: 0 auto; padding: 2px 14px 16px; }
.cal-weekdays{ display: grid; grid-template-columns: repeat(7, 1fr); padding: 4px 4px 6px; }
.cal-weekdays span{ text-align:center; font-size: 10px; font-weight: 600; color: var(--ink-soft); text-transform: uppercase; letter-spacing: 0.06em; }
.cal-days{ display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.day-cell{
  aspect-ratio: 1 / 0.85; border-radius: 8px; padding: 5px 6px 4px; position: relative;
  cursor: pointer; border: 1px solid transparent; transition: background .15s ease, border-color .15s ease;
  display:flex; flex-direction:column; justify-content: center; align-items: center;
}
.day-cell:hover{ background: var(--surface-soft); }
.day-cell.muted{ opacity: 0.35; cursor:default; }
.day-cell.muted:hover{ background: transparent; }
.day-cell.today{ border-color: var(--medico); background: var(--medico-bg); }
.day-num{ font-size: 11.5px; font-weight: 600; font-family: 'Inter', sans-serif; font-variant-numeric: tabular-nums; font-weight: 500; }
.day-dots{ display:flex; gap: 4px; flex-wrap: wrap; justify-content: center; position: absolute; bottom: 8px; left: 0; right: 0; }
.day-dots .dot{ width:6.5px; height:6.5px; border-radius:50%; }

.sidebar{ display:flex; flex-direction:column; gap: 16px; }
.sidebar-head{ display:flex; align-items:baseline; justify-content: space-between; padding: 4px 2px 0; }
.sidebar-head h2{ font-size: 17px; font-weight: 600; margin:0; }
.sidebar-head span{ font-size: 12.5px; color: var(--ink-soft); font-family: 'Inter', sans-serif; font-variant-numeric: tabular-nums; font-weight: 500; }

.appt-card{
  background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-md);
  padding: 14px 16px; display:flex; gap: 13px; cursor: pointer; box-shadow: var(--shadow);
  transition: transform .15s ease, border-color .15s ease;
}
.appt-card:hover{ transform: translateY(-1px); border-color: var(--ink-soft); }
.appt-date{
  flex-shrink:0; width: 46px; height: 50px; border-radius: 10px;
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  font-family: 'Inter', sans-serif; font-variant-numeric: tabular-nums; font-weight: 500;
}
.appt-date .d{ font-size: 17px; font-weight: 700; line-height:1; }
.appt-date .m{ font-size: 10px; text-transform:uppercase; letter-spacing:.05em; margin-top:2px; }

.appt-body{ flex:1; min-width:0; }
.appt-top{ display:flex; align-items:center; justify-content:space-between; gap:8px; }
.appt-title{ font-size: 14.5px; font-weight: 600; line-height:1.3; }
.appt-time{ font-size: 12px; color: var(--ink-soft); font-family: 'Inter', sans-serif; font-variant-numeric: tabular-nums; font-weight: 500; flex-shrink:0; }
.appt-meta{ display:flex; align-items:center; gap:6px; margin-top: 5px; font-size: 12.5px; color: var(--ink-soft); }

.type-tag{ font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.04em; }
.recurring-tag{ display:inline-flex; align-items:center; gap:3px; font-size: 10.5px; color: var(--ink-soft); }
.recurring-tag svg{ width:10px; height:10px; }

.fab{
  position: fixed; right: 34px; bottom: 34px;
  background: var(--medico); color: white; border: none; border-radius: 999px;
  padding: 15px 22px 15px 18px; display:flex; align-items:center; gap: 9px;
  font-family:'Inter', sans-serif; font-size: 14.5px; font-weight: 600; cursor: pointer;
  box-shadow: 0 10px 30px -8px rgba(61,107,102,0.55); transition: transform .15s ease;
}
.fab:hover{ transform: translateY(-2px); }

/* Modal */
.overlay{
  position: fixed; inset:0; background: rgba(31,43,39,0.38); backdrop-filter: blur(2px);
  display:flex; align-items:center; justify-content:center; z-index: 50;
  opacity:0; pointer-events:none; transition: opacity .2s ease;
}
.overlay.show{ opacity:1; pointer-events:auto; }
.modal{
  background: var(--surface); width: 420px; max-width: calc(100vw - 40px);
  border-radius: var(--radius-lg); box-shadow: 0 30px 60px -15px rgba(31,43,39,0.35);
  transform: translateY(14px); transition: transform .2s ease; overflow:hidden;
}
.overlay.show .modal{ transform: translateY(0); }

.modal-head{ padding: 22px 24px 14px; display:flex; align-items:flex-start; justify-content:space-between; gap: 12px; }
.modal-head .type-tag{ margin-bottom: 8px; display:inline-block;}
.modal-title{ font-size: 19px; font-weight: 600; line-height:1.3; }
.modal-close{
  width: 30px; height:30px; border-radius:50%; border:1px solid var(--line);
  background: var(--surface); display:flex; align-items:center; justify-content:center;
  cursor:pointer; flex-shrink:0; color: var(--ink-soft);
}
.modal-body{ padding: 4px 24px 24px; display:flex; flex-direction:column; gap: 14px; }
.info-row{ display:flex; gap: 12px; align-items:flex-start; }
.info-row .icon{
  width: 34px; height:34px; border-radius: 9px; background: var(--surface-soft);
  display:flex; align-items:center; justify-content:center; flex-shrink:0; color: var(--medico);
}
.info-row .label{ font-size: 11px; color: var(--ink-soft); text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px; }
.info-row .value{ font-size: 14px; font-weight: 500; line-height:1.4; }
.modal-actions{ display:flex; gap: 10px; padding: 4px 24px 24px; }
.btn{ flex:1; padding: 11px; border-radius: var(--radius-sm); border: none; font-family:'Inter', sans-serif; font-size: 13.5px; font-weight: 600; cursor: pointer; }
.btn-primary{ background: var(--medico); color: white; }
.btn-ghost{ background: var(--surface-soft); color: var(--ink); }

/* Auth CSS */
.login-wrapper {
  display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px;
}
.login-box {
  width: 100%; max-width: 400px; padding: 32px;
}
.login-form {
  display: flex; flex-direction: column;
}
.login-form label {
  font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;
}
.input-field {
  padding: 12px 14px; border-radius: 8px; border: 1px solid var(--line); font-family: 'Inter', sans-serif; font-size: 14px;
  background: var(--bg); color: var(--ink); transition: border-color .2s, background .2s;
}
.input-field:focus { outline: none; border-color: var(--medico); background: var(--surface); }
.login-error {
  background: var(--pagos-bg); color: var(--pagos); padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; text-align: center; font-weight: 500;
}


/* Pagination CSS */
.sidebar-pagination {
  display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: auto; padding-top: 10px;
}
.page-btn {
  width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--line);
  background: var(--surface); display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--ink); transition: all .15s ease;
}
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-btn:not(:disabled):hover { background: var(--surface-soft); }
.page-numbers { display: flex; gap: 4px; }
.page-num {
  width: 28px; height: 28px; border-radius: 6px; border: none; background: transparent;
  color: var(--ink-soft); font-size: 13px; font-weight: 600; cursor: pointer; transition: all .15s ease;
}
.page-num.active { background: var(--ink); color: var(--bg); }
.page-num:not(.active):hover { background: var(--surface-soft); }

@media (max-width: 880px){

  .layout{ grid-template-columns: 1fr; }
  .app{ padding: 20px 16px 90px; }
  .cal-title{ font-size: 22px; }
}
</style>
