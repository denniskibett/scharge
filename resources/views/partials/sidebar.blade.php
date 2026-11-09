<aside
  :class="sidebarToggle ? 'translate-x-0 lg:w-[90px]' : '-translate-x-full'"
  class="sidebar fixed left-0 top-0 z-9999 flex h-screen w-[290px] flex-col overflow-y-hidden border-r border-gray-200 bg-white px-5 dark:border-gray-800 dark:bg-black lg:static lg:translate-x-0"
>
  <!-- SIDEBAR HEADER -->
  <div
    :class="sidebarToggle ? 'justify-center' : 'justify-between'"
    class="flex items-center gap-2 pt-8 sidebar-header pb-7"
  >
    <a href="{{ route('dashboard') }}">
      <span class="logo" :class="sidebarToggle ? 'hidden' : ''">
        <img
          class="dark:hidden"
          src="{{ asset('assets/images/brand/scharge-logo.svg') }}"
          alt="{{ SystemHelper::appName() }}"
          width="150"
          height="36"
        />
        <img
          class="hidden dark:block"
          src="{{ asset('assets/images/brand/scharge-logo-white.svg') }}"
          alt="{{ SystemHelper::appName() }}"
          width="150"
          height="36"
        />
      </span>

      <img
        class="logo-icon"
        :class="sidebarToggle ? 'lg:block' : 'hidden'"
        src="{{ asset('assets/images/brand/scharge-icon.svg') }}"
        alt="{{ SystemHelper::appName() }}"
        width="32"
        height="32"
      />
    </a>
  </div>
  <!-- SIDEBAR HEADER -->

  <div
    class="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar"
  >
    <!-- Sidebar Menu -->
    <nav x-data="sidebarMenu">
      <!-- Menu Groups -->
      <template x-for="(group, groupIndex) in filteredMenuData" :key="groupIndex">
        <div>
          <h3 class="mb-4 text-xs uppercase leading-[20px] text-gray-400">
            <span
              class="menu-group-title"
              :class="sidebarToggle ? 'lg:hidden' : ''"
              x-text="group.title"
            ></span>

            <svg
              :class="sidebarToggle ? 'lg:block hidden' : 'hidden'"
              class="mx-auto fill-current menu-group-icon"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                fill-rule="evenodd"
                clip-rule="evenodd"
                d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z"
                fill=""
              />
            </svg>
          </h3>

          <ul class="flex flex-col gap-4 mb-6">
            <template x-for="(item, itemIndex) in group.items" :key="itemIndex">
              <li x-show="hasPermission(item)">
                <a
                  :href="item.children ? '#' : item.link"
                  @click.prevent="item.children ? toggleSelected(item.name) : setActive(item.page, item.label, item.link)"
                  class="relative flex items-center gap-3 rounded-lg px-4 py-2.5 font-medium duration-300 ease-in-out cursor-pointer"
                  :class="getItemClasses(item)"
                >
                  <!-- Icon -->
                  <svg
                    :class="getIconClasses(item)"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                  >
                    <path x-show="!item.stroke" fill-rule="evenodd" clip-rule="evenodd" :d="item.icon" class="fill-current" />
                    <!-- Outline icons (Heroicons, MIT) -->
                    <path x-show="item.stroke" :d="item.icon" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>

                  <!-- Text -->
                  <span
                    class="menu-item-text"
                    :class="sidebarToggle ? 'lg:hidden' : ''"
                    x-text="item.label"
                  ></span>

                  <!-- Dropdown Arrow -->
                  <svg
                    x-show="item.children"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 stroke-current w-5 h-5"
                    :class="[getArrowClasses(item), sidebarToggle ? 'lg:hidden' : '' ]"
                    width="20"
                    height="20"
                    viewBox="0 0 20 20"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                  >
                    <path
                      d="M4.79175 7.39584L10.0001 12.6042L15.2084 7.39585"
                      stroke-width="1.5"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                </a>

                <!-- Dropdown Menu -->
                <div
                  x-show="item.children && isSelected(item.name)"
                  class="overflow-hidden transform translate"
                >
                  <ul
                    :class="sidebarToggle ? 'lg:hidden' : 'flex'"
                    class="flex flex-col gap-1 mt-2 pl-9"
                  >
                    <template x-for="(child, childIndex) in item.children" :key="childIndex">
                      <li x-show="hasPermission(child)">
                        <a
                          :href="child.link"
                          @click="$event.preventDefault() || setActive(child.page, child.label, child.link)"
                          class="relative flex items-center rounded-lg px-4 py-2.5 font-medium duration-300 ease-in-out cursor-pointer"
                          :class="isActive(child.page) ? 'text-sm text-primary bg-primary-10' : 'text-sm text-gray-600 dark:text-gray-400 hover:text-primary hover:bg-primary-10'"
                          x-text="child.label"
                        ></a>
                      </li>
                    </template>
                  </ul>
                </div>
              </li>
            </template>
          </ul>
        </div>
      </template>
    </nav>
    <!-- Sidebar Menu -->

  </div>
</aside>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('sidebarMenu', () => ({
    selected: Alpine.$persist(''), // Start with no dropdowns open
    activePage: Alpine.$persist('dashboard'),
    activeItemLabel: Alpine.$persist('Dashboard'),
    
    // FIXED: Use Spatie's native method directly
    userRole: @js(auth()->user()->getRoleNames()->first() ?? 'guest'),
    // From the user's real permissions (App\Support\Access::menuFor); null = use the role list below.
    allowedMenu: @js(\App\Support\Access::menuFor(auth()->user())),
    
    // Role-based permissions mapping
    rolePermissions: {
      'sysadmin': [
        'dashboard', 'calendar', 'profile',
        'property', 'properties', 'units', 'tenants', 'tenancies',
        'finance', 'invoices', 'payments', 'payees', 'expenses',
        'water', 'water_readings', 'water_reports',
        'maintenance',
        'gate', 'security', 'security_logs',
        'sms', 'sms_send', 'sms_history', 'sms_templates', 'sms_settings',
        'crm', 'crm_pipeline', 'crm_follow_ups', 'crm_demos', 'crm_waitlist', 'crm_feedback',
        'administration', 'companies', 'users', 'staff', 'team', 'roles', 'system_settings', 'clear_cache',
        'settings', 'integrations', 'api_keys', 'property_claims', 'server_security',
      ],
      // scharge staff: manages a book of companies, their leads and revenue.
      'account_manager': [
        'dashboard', 'calendar', 'profile',
        'crm', 'crm_pipeline', 'crm_follow_ups', 'crm_demos', 'crm_waitlist', 'crm_feedback',
        'administration', 'companies', 'property_claims',
      ],
      'admin': [
        'dashboard', 'calendar', 'profile',
        'property', 'properties', 'units', 'tenants', 'tenancies',
        'finance', 'invoices', 'payments', 'payees', 'expenses',
        'water', 'water_readings', 'water_reports',
        'maintenance',
        'gate', 'security', 'security_logs',
        'sms', 'sms_send', 'sms_history', 'sms_templates',
        'users', 'staff', 'team',
        'settings', 'integrations', 'api_keys',
      ],
      'property_manager': [
        'dashboard', 'calendar', 'profile',
        'property', 'properties', 'units', 'tenants', 'tenancies',
        'sms', 'sms_send', 'sms_history', 'sms_templates', 
        'maintenance',
      ],
      'accountant': [
        'dashboard', 'calendar', 'profile',
        'finance', 'invoices', 'payments', 'payees', 'expenses',
        'users', 'staff', 'team',
      ],
      'meter_reader': [
        'dashboard', 'calendar', 'profile',
        'water', 'water_readings'
      ],
      'cleaning_staff': [
        'dashboard', 'calendar', 'profile',
        'maintenance'
      ],
      'maintenance': [
        'dashboard', 'calendar', 'profile',
        'maintenance'
      ],
      'security': [
        'dashboard', 'calendar', 'profile',
        'gate', 'security', 'security_logs'
      ],
      'tenant': [
        'dashboard', 'calendar', 'profile',
        'finance', 'invoices', 'payments',
        'property', 'tenancies',
        'maintenance',
        'gate', 'security', 'security_logs'
      ],
      // BnB host: their own units only.
      'bnb_host': [
        'dashboard', 'calendar', 'profile', 'host', 'host_units', 'gate',
      ],
      'guest': [
        'dashboard', 'profile'
      ]
    },

    // Complete Menu Data Structure
    menuData: [
      {
        title: 'MAIN',
        items: [
          { name: 'Dashboard', label: 'Dashboard', link: '/dashboard', page: 'dashboard', permission: 'dashboard',
            icon: 'M5.5 3.25C4.25736 3.25 3.25 4.25736 3.25 5.5V8.99998C3.25 10.2426 4.25736 11.25 5.5 11.25H9C10.2426 11.25 11.25 10.2426 11.25 8.99998V5.5C11.25 4.25736 10.2426 3.25 9 3.25H5.5ZM4.75 5.5C4.75 5.08579 5.08579 4.75 5.5 4.75H9C9.41421 4.75 9.75 5.08579 9.75 5.5V8.99998C9.75 9.41419 9.41421 9.74998 9 9.74998H5.5C5.08579 9.74998 4.75 9.41419 4.75 8.99998V5.5ZM5.5 12.75C4.25736 12.75 3.25 13.7574 3.25 15V18.5C3.25 19.7426 4.25736 20.75 5.5 20.75H9C10.2426 20.75 11.25 19.7427 11.25 18.5V15C11.25 13.7574 10.2426 12.75 9 12.75H5.5ZM4.75 15C4.75 14.5858 5.08579 14.25 5.5 14.25H9C9.41421 14.25 9.75 14.5858 9.75 15V18.5C9.75 18.9142 9.41421 19.25 9 19.25H5.5C5.08579 19.25 4.75 18.9142 4.75 18.5V15ZM12.75 5.5C12.75 4.25736 13.7574 3.25 15 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V8.99998C20.75 10.2426 19.7426 11.25 18.5 11.25H15C13.7574 11.25 12.75 10.2426 12.75 8.99998V5.5ZM15 4.75C14.5858 4.75 14.25 5.08579 14.25 5.5V8.99998C14.25 9.41419 14.5858 9.74998 15 9.74998H18.5C18.9142 9.74998 19.25 9.41419 19.25 8.99998V5.5C19.25 5.08579 18.9142 4.75 18.5 4.75H15ZM15 12.75C13.7574 12.75 12.75 13.7574 12.75 15V18.5C12.75 19.2426 13.7574 20.75 15 20.75H18.5C19.7426 20.75 20.75 19.7427 20.75 18.5V15C20.75 13.7574 19.7426 12.75 18.5 12.75H15ZM14.25 15C14.25 14.5858 14.5858 14.25 15 14.25H18.5C18.9142 14.25 19.25 14.5858 19.25 15V18.5C19.25 18.9142 18.9142 19.25 18.5 19.25H15C14.5858 19.25 14.25 18.9142 14.25 18.5V15Z' },
          { name: 'Calendar', label: 'Calendar', link: '/calendar', page: 'calendar', permission: 'calendar',
            icon: 'M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z' },
          { name: 'Reports', label: 'Reports', link: '/reports', page: 'reports', permission: 'reports',
            icon: 'M4 3.75a.75.75 0 0 1 .75.75v14.75h14.75a.75.75 0 0 1 0 1.5H4.75A1.5 1.5 0 0 1 3.25 19.25V4.5A.75.75 0 0 1 4 3.75Zm4 9.5a.75.75 0 0 1 .75.75v2.5a.75.75 0 0 1-1.5 0V14a.75.75 0 0 1 .75-.75Zm4-4a.75.75 0 0 1 .75.75v6.5a.75.75 0 0 1-1.5 0V10a.75.75 0 0 1 .75-.75Zm4-3a.75.75 0 0 1 .75.75v9.5a.75.75 0 0 1-1.5 0V7a.75.75 0 0 1 .75-.75Z' },
          { name: 'Host', label: 'My BnB units', link: '/host', page: 'host', permission: 'host',
            icon: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5Z' }
        ]
      },
      {
        title: 'RUN THE PROPERTIES',
        items: [
          { name: 'properties', label: 'Properties', link: '/properties', page: 'properties', permission: 'properties',
            stroke: true, icon: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z' },
          { name: 'units', label: 'Units', link: '/units', page: 'units', permission: 'units',
            stroke: true, icon: 'M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819' },
          { name: 'tenants', label: 'Tenants', link: '/tenants', page: 'tenants', permission: 'tenants',
            stroke: true, icon: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z' },
          { name: 'tenancies', label: 'Tenancies', link: '/tenancies', page: 'tenancies', permission: 'tenancies',
            stroke: true, icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z' },
          { name: 'waterReadings', label: 'Water readings', link: '/water', page: 'waterReadings', permission: 'water_readings',
            stroke: true, icon: 'M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5' },
          { name: 'Maintenance', label: 'Repairs', link: '/maintenance', page: 'maintenance', permission: 'maintenance',
            stroke: true, icon: 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z' },
          { name: 'Gate', label: 'Guests & gate', link: '/gate', page: 'gate', permission: 'gate',
            stroke: true, icon: 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z' },
          { name: 'Security', label: 'Gate log', link: '/security/logs', page: 'security', permission: 'security',
            stroke: true, icon: 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z' }
        ]
      },
      {
        title: 'MONEY',
        items: [
          { name: 'invoices', label: 'Invoices', link: '/invoices', page: 'invoices', permission: 'invoices',
            stroke: true, icon: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z' },
          { name: 'payments', label: 'Payments', link: '/payments', page: 'payments', permission: 'payments',
            stroke: true, icon: 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z' },
          { name: 'expenses', label: 'Expenses', link: '/expenses', page: 'expenses', permission: 'expenses',
            stroke: true, icon: 'm9 14.25 6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z' },
          { name: 'payees', label: 'Payees', link: '/payees', page: 'payees', permission: 'payees',
            stroke: true, icon: 'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' }
        ]
      },
      {
        title: 'TENANT MESSAGES',
        items: [
          {
            name: 'SMS',
            label: 'SMS',
            link: '/#',
            page: 'sms',
            permission: 'sms',
            icon: 'M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM12 20C7.58172 20 4 16.4183 4 12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20ZM8 8H16V10H8V8ZM8 12H16V14H8V12ZM8 16H13V18H8V16Z',
            children: [
              { label: 'Send SMS', link: '/sms/broadcast', page: 'smsSend', permission: 'sms_send' },
              { label: 'History', link: '/sms/history', page: 'smsHistory', permission: 'sms_history' },
              { label: 'Templates', link: '/sms/templates', page: 'smsTemplates', permission: 'sms_templates' },
              { label: 'Settings', link: '/sms/settings', page: 'smsSettings', permission: 'sms_settings' }
            ]
          }
        ]
      },
      {
        title: 'SALES & ACCOUNTS',
        items: [
          { name: 'crmPipeline', label: 'Pipeline', link: '/crm', page: 'crmPipeline', permission: 'crm_pipeline',
            stroke: true, icon: 'M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z' },
          { name: 'crmFollowUps', label: 'Follow-ups', link: '/crm/follow-ups', page: 'crmFollowUps', permission: 'crm_follow_ups',
            stroke: true, icon: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' },
          { name: 'crmDemos', label: 'Requests', link: '/crm/requests', page: 'crmDemos', permission: 'crm_demos',
            stroke: true, icon: 'M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3' },
          { name: 'crmWaitlist', label: 'Waitlist', link: '/crm/waitlist', page: 'crmWaitlist', permission: 'crm_waitlist',
            stroke: true, icon: 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z' },
          { name: 'crmFeedback', label: 'Feedback', link: '/crm/feedback', page: 'crmFeedback', permission: 'crm_feedback',
            stroke: true, icon: 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z' },
          { name: 'websiteAnalytics', label: 'Website analytics', link: '/admin/website-analytics', page: 'websiteAnalytics', permission: 'crm',
            stroke: true, icon: 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418' },
          { name: 'companies', label: 'Companies', link: '/admin/companies', page: 'companies', permission: 'companies',
            stroke: true, icon: 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z' },
          { name: 'propertyClaims', label: 'Building claims', link: '/admin/property-claims', page: 'propertyClaims', permission: 'property_claims',
            stroke: true, icon: 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75' }
        ]
      },
      {
        title: 'SETUP',
        items: [
          { name: 'integrations', label: 'Integrations', link: '/integrations', page: 'integrations', permission: 'integrations',
            stroke: true, icon: 'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0 .582-4.717.532.532 0 0 0-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 0 0 .658-.663 48.422 48.422 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z' },
          { name: 'serverSecurity', label: 'Server security', link: '/admin/security', page: 'serverSecurity', permission: 'server_security',
            stroke: true, icon: 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z' },
          { name: 'apiKeys', label: 'API keys', link: '/api-keys', page: 'apiKeys', permission: 'api_keys',
            stroke: true, icon: 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z' },
          { name: 'team', label: 'Team & access', link: '/team', page: 'team', permission: 'team',
            stroke: true, icon: 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z' },
          { name: 'roles', label: 'Roles & permissions', link: '/roles', page: 'roles', permission: 'roles',
            stroke: true, icon: 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z' },
          { name: 'system', label: 'System', link: '/system', page: 'system', permission: 'system_settings',
            stroke: true, icon: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' }
        ]
      }
    ],

    // Computed property for filtered menu data based on role permissions
    get filteredMenuData() {
      const userPermissions = this.getUserPermissions();
      
      return this.menuData
        .map(group => {
          const filteredItems = group.items
            .map(item => {
              if (item.children) {
                const filteredChildren = item.children.filter(child => 
                  userPermissions.includes(child.permission)
                );
                
                if (filteredChildren.length > 0 || userPermissions.includes(item.permission)) {
                  return {
                    ...item,
                    children: filteredChildren
                  };
                }
                return null;
              }
              return userPermissions.includes(item.permission) ? item : null;
            })
            .filter(item => item !== null);
          
          return filteredItems.length > 0 ? { ...group, items: filteredItems } : null;
        })
        .filter(group => group !== null);
    },

    // Helper method to get user permissions with error handling
    getUserPermissions() {
      if (Array.isArray(this.allowedMenu)) return this.allowedMenu;
      if (!this.rolePermissions[this.userRole]) {
        console.error(
          `[Sidebar] Unknown user role: "${this.userRole}". ` +
          `Available roles: ${Object.keys(this.rolePermissions).join(', ')}. ` +
          `Falling back to "guest" permissions.`
        );
        
        return this.rolePermissions['guest'];
      }
      
      return this.rolePermissions[this.userRole];
    },

    init() {
      this.setInitialActivePage();
      window.addEventListener('spa:navigated', () => this.setInitialActivePage());
    },

    hasPermission(item) {
      const userPermissions = this.getUserPermissions();
      
      if (item.children) {
        return item.children.some(child => userPermissions.includes(child.permission));
      }
      
      return userPermissions.includes(item.permission);
    },

    toggleSelected(itemName) {
      this.selected = this.selected === itemName ? '' : itemName;
    },

    setActive(page, label, link) {
      this.activePage = page;
      this.activeItemLabel = label;
      if (link && link !== '#' && link !== '/#') {
        window.spaVisit ? window.spaVisit(link) : (window.location.href = link);
      }
    },

    // FIXED: Removed duplicate findParentItem - only one version remains
    findParentItem(page) {
      if (!page) return null;
      
      for (const group of this.menuData) {
        for (const item of group.items) {
          if (item.children) {
            for (const child of item.children) {
              if (child.page === page) {
                return item;
              }
            }
          }
        }
      }
      return null;
    },

    setInitialActivePage() {
      // The menu entry whose link is the longest match for the current path.
      const path = window.location.pathname;
      let best = { page: 'dashboard', len: 0, label: 'Dashboard' };
      const consider = (entry, parent) => {
        const l = entry.link;
        if (l && l !== '/#' && (path === l || path.startsWith(l + '/')) && l.length > best.len) best = { page: entry.page, len: l.length, label: entry.label, parent };
      };
      this.menuData.forEach(group => group.items.forEach(item => {
        consider(item, '');
        (item.children || []).forEach(child => consider(child, item.name)); // e.g. SMS › Send SMS
      }));
      this.activePage = best.page;
      this.activeItemLabel = best.label;
      this.selected = best.parent || this.selected; // keep an opened group (e.g. SMS) open
    },

    setActiveItemLabel(page) {
      for (const group of this.menuData) {
        for (const item of group.items) {
          if (!item.children && item.page === page) {
            this.activeItemLabel = item.label;
            return;
          }
          if (item.children) {
            for (const child of item.children) {
              if (child.page === page) {
                this.activeItemLabel = child.label;
                return;
              }
            }
          }
        }
      }
    },

    isSelected(itemName) {
      return this.selected === itemName;
    },

    isActive(page) {
      // Ensure we only return true for exact page matches
      // and ignore undefined/null values
      if (!page) return false;
      return this.activePage === page;
    },

    getItemClasses(item) {
      // For items without children (leaf items)
      if (!item.children) {
        // Only active if the page matches exactly
        if (this.isActive(item.page)) {
          return 'text-sm bg-primary-10 text-primary';
        }
        return 'text-sm text-gray-600 dark:text-gray-400 hover:bg-primary-10 hover:text-primary';
      } 
      // For items with children (parent items)
      else {
        // A parent item is active ONLY IF:
        // 1. It has a page property AND that page is active, OR
        // 2. A child is active AND the parent has been selected/opened
        const hasActiveChild = this.isChildActive(item);
        
        // Only mark parent as active if it's explicitly selected or has an active child
        // AND we're on a page that belongs to this parent
        const isParentActive = (this.selected === item.name) && hasActiveChild;
        
        // Special case: Don't mark parent as active if no child is active
        // This prevents parent items from being active by default
        if (!hasActiveChild) {
          return 'text-sm text-gray-600 dark:text-gray-400 hover:bg-primary-10 hover:text-primary';
        }
        
        return isParentActive || hasActiveChild
          ? 'text-sm bg-primary-10 text-primary' 
          : 'text-sm text-gray-600 dark:text-gray-400 hover:bg-primary-10 hover:text-primary';
      }
    },

    getIconClasses(item) {
      if (!item.children) {
        return this.isActive(item.page)
          ? 'text-sm text-primary fill-current'
          : 'text-sm text-gray-600 dark:text-gray-400 group-hover:text-primary fill-current';
      } else {
        const hasActiveChild = this.isChildActive(item);
        const isParentActive = (this.selected === item.name) && hasActiveChild;
        
        if (!hasActiveChild) {
          return 'text-sm text-gray-600 dark:text-gray-400 group-hover:text-primary fill-current';
        }
        
        return isParentActive || hasActiveChild
          ? 'text-sm text-primary fill-current'
          : 'text-sm text-gray-600 dark:text-gray-400 group-hover:text-primary fill-current';
      }
    },

    getArrowClasses(item) {
      if (!item.children) return '';
      return this.selected === item.name 
        ? 'rotate-180 text-primary' 
        : 'text-sm text-gray-600 dark:text-gray-400';
    },

    isChildActive(item) {
      if (!item.children) return false;
      return item.children.some(child => this.isActive(child.page));
    }
  }));
});
</script>