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
                  @click="item.children ? toggleSelected(item.name) : $event.preventDefault() || setActive(item.page, item.label, item.link)"
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
                    <path
                      fill-rule="evenodd"
                      clip-rule="evenodd"
                      :d="item.icon"
                      class="fill-current"
                    />
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
                          @click="setActive(child.page, child.label, child.link)"
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
    
    // Role-based permissions mapping
    rolePermissions: {
      'sysadmin': [
        'dashboard', 'calendar', 'profile',
        'property', 'properties', 'units', 'tenants', 'tenancies',
        'finance', 'invoices', 'payments', 'payees', 'expenses',
        'water', 'water_readings', 'water_reports',
        'maintenance',
        'security', 'security_logs',
        'sms', 'sms_send', 'sms_history', 'sms_templates', 'sms_settings',
        'crm', 'crm_pipeline', 'crm_follow_ups', 'crm_demos', 'crm_waitlist', 'crm_feedback',
        'administration', 'companies', 'users', 'staff', 'roles', 'system_settings', 'clear_cache',
        'settings', 'integrations', 'api_keys', 'property_claims',
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
        'security', 'security_logs',
        'sms', 'sms_send', 'sms_history', 'sms_templates',
        'users', 'staff',
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
        'users', 'staff',
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
        'security', 'security_logs'
      ],
      'tenant': [
        'dashboard', 'calendar', 'profile',
        'finance', 'invoices', 'payments',
        'property', 'tenancies',
        'maintenance',
        'security', 'security_logs'
      ],
      // BnB host: their own units only.
      'bnb_host': [
        'dashboard', 'calendar', 'profile', 'host', 'host_units',
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
          {
            name: 'Dashboard',
            label: 'Dashboard',
            link: '/dashboard',
            page: 'dashboard',
            permission: 'dashboard',
            icon: 'M5.5 3.25C4.25736 3.25 3.25 4.25736 3.25 5.5V8.99998C3.25 10.2426 4.25736 11.25 5.5 11.25H9C10.2426 11.25 11.25 10.2426 11.25 8.99998V5.5C11.25 4.25736 10.2426 3.25 9 3.25H5.5ZM4.75 5.5C4.75 5.08579 5.08579 4.75 5.5 4.75H9C9.41421 4.75 9.75 5.08579 9.75 5.5V8.99998C9.75 9.41419 9.41421 9.74998 9 9.74998H5.5C5.08579 9.74998 4.75 9.41419 4.75 8.99998V5.5ZM5.5 12.75C4.25736 12.75 3.25 13.7574 3.25 15V18.5C3.25 19.7426 4.25736 20.75 5.5 20.75H9C10.2426 20.75 11.25 19.7427 11.25 18.5V15C11.25 13.7574 10.2426 12.75 9 12.75H5.5ZM4.75 15C4.75 14.5858 5.08579 14.25 5.5 14.25H9C9.41421 14.25 9.75 14.5858 9.75 15V18.5C9.75 18.9142 9.41421 19.25 9 19.25H5.5C5.08579 19.25 4.75 18.9142 4.75 18.5V15ZM12.75 5.5C12.75 4.25736 13.7574 3.25 15 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V8.99998C20.75 10.2426 19.7426 11.25 18.5 11.25H15C13.7574 11.25 12.75 10.2426 12.75 8.99998V5.5ZM15 4.75C14.5858 4.75 14.25 5.08579 14.25 5.5V8.99998C14.25 9.41419 14.5858 9.74998 15 9.74998H18.5C18.9142 9.74998 19.25 9.41419 19.25 8.99998V5.5C19.25 5.08579 18.9142 4.75 18.5 4.75H15ZM15 12.75C13.7574 12.75 12.75 13.7574 12.75 15V18.5C12.75 19.2426 13.7574 20.75 15 20.75H18.5C19.7426 20.75 20.75 19.7427 20.75 18.5V15C20.75 13.7574 19.7426 12.75 18.5 12.75H15ZM14.25 15C14.25 14.5858 14.5858 14.25 15 14.25H18.5C18.9142 14.25 19.25 14.5858 19.25 15V18.5C19.25 18.9142 18.9142 19.25 18.5 19.25H15C14.5858 19.25 14.25 18.9142 14.25 18.5V15Z'
          },
          {
            name: 'Calendar',
            label: 'Calendar',
            link: '/calendar',
            page: 'calendar',
            permission: 'calendar',
            icon: 'M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z'
          },
          {
            name: 'Host',
            label: 'My BnB units',
            link: '/host',
            page: 'host',
            permission: 'host',
            icon: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5Z'
          }
        ]
      },
      {
        title: 'RUN THE PROPERTIES',
        items: [
          {
            name: 'Property',
            label: 'Lettings',
            link: '/#',
            page: 'properties',
            permission: 'property',
            icon: 'M19.5 2.25H4.5C4.08579 2.25 3.75 2.58579 3.75 3V21C3.75 21.4142 4.08579 21.75 4.5 21.75H19.5C19.9142 21.75 20.25 21.4142 20.25 21V3C20.25 2.58579 19.9142 2.25 19.5 2.25ZM4.5 3.75H19.5V6.75H4.5V3.75ZM4.5 8.25H19.5V20.25H4.5V8.25ZM7.5 11.25C7.08579 11.25 6.75 11.5858 6.75 12V15C6.75 15.4142 7.08579 15.75 7.5 15.75H10.5C10.9142 15.75 11.25 15.4142 11.25 15V12C11.25 11.5858 10.9142 11.25 10.5 11.25H7.5Z',
            children: [
              { label: 'Properties', link: '/properties', page: 'properties', permission: 'properties' },
              { label: 'Units', link: '/units', page: 'units', permission: 'units' },
              { label: 'Tenants', link: '/tenants', page: 'tenants', permission: 'tenants' },
              { label: 'Tenancies', link: '/tenancies', page: 'tenancies', permission: 'tenancies' }
            ]
          },
          {
            name: 'Water',
            label: 'Meter readings',
            link: '/#',
            page: 'water',
            permission: 'water',
            icon: 'M12 2C12.4142 2 12.75 2.33579 12.75 2.75V4.25C16.1668 4.25 19.25 7.33317 19.25 10.75V18.5C19.25 20.0188 18.0188 21.25 16.5 21.25H7.5C5.98122 21.25 4.75 20.0188 4.75 18.5V10.75C4.75 7.33317 7.83317 4.25 11.25 4.25V2.75C11.25 2.33579 11.5858 2 12 2ZM11.25 5.75C8.48858 5.75 6.25 7.98858 6.25 10.75V18.5C6.25 19.1904 6.80964 19.75 7.5 19.75H16.5C17.1904 19.75 17.75 19.1904 17.75 18.5V10.75C17.75 7.98858 15.5114 5.75 12.75 5.75H11.25ZM12 9.25C12.4142 9.25 12.75 9.58579 12.75 10V15C12.75 15.4142 12.4142 15.75 12 15.75C11.5858 15.75 11.25 15.4142 11.25 15V10C11.25 9.58579 11.5858 9.25 12 9.25Z',
            children: [
              { label: 'Water readings', link: '/water', page: 'waterReadings', permission: 'water_readings' },
              { label: 'Water reports', link: '/water/reports', page: 'waterReports', permission: 'water_reports' }
            ]
          },
          {
            name: 'Maintenance',
            label: 'Repairs',
            link: '/maintenance',
            page: 'maintenance',
            permission: 'maintenance',
            icon: 'M12 2C12.4142 2 12.75 2.33579 12.75 2.75V4.25C16.1668 4.25 19.25 7.33317 19.25 10.75V18.5C19.25 20.0188 18.0188 21.25 16.5 21.25H7.5C5.98122 21.25 4.75 20.0188 4.75 18.5V10.75C4.75 7.33317 7.83317 4.25 11.25 4.25V2.75C11.25 2.33579 11.5858 2 12 2ZM11.25 5.75C8.48858 5.75 6.25 7.98858 6.25 10.75V18.5C6.25 19.1904 6.80964 19.75 7.5 19.75H16.5C17.1904 19.75 17.75 19.1904 17.75 18.5V10.75C17.75 7.98858 15.5114 5.75 12.75 5.75H11.25ZM12 9.25C12.4142 9.25 12.75 9.58579 12.75 10V15C12.75 15.4142 12.4142 15.75 12 15.75C11.5858 15.75 11.25 15.4142 11.25 15V10C11.25 9.58579 11.5858 9.25 12 9.25Z'
          },
          {
            name: 'Security',
            label: 'Gate & visitors',
            link: '/security/logs',
            page: 'security',
            permission: 'security',
            icon: 'M12 2C12.4142 2 12.75 2.33579 12.75 2.75V4.25C16.1668 4.25 19.25 7.33317 19.25 10.75V18.5C19.25 20.0188 18.0188 21.25 16.5 21.25H7.5C5.98122 21.25 4.75 20.0188 4.75 18.5V10.75C4.75 7.33317 7.83317 4.25 11.25 4.25V2.75C11.25 2.33579 11.5858 2 12 2ZM11.25 5.75C8.48858 5.75 6.25 7.98858 6.25 10.75V18.5C6.25 19.1904 6.80964 19.75 7.5 19.75H16.5C17.1904 19.75 17.75 19.1904 17.75 18.5V10.75C17.75 7.98858 15.5114 5.75 12.75 5.75H11.25ZM12 9.25C12.4142 9.25 12.75 9.58579 12.75 10V15C12.75 15.4142 12.4142 15.75 12 15.75C11.5858 15.75 11.25 15.4142 11.25 15V10C11.25 9.58579 11.5858 9.25 12 9.25Z'
          }
        ]
      },
      {
        title: 'MONEY',
        items: [
          {
            name: 'Finance',
            label: 'Rent & billing',
            link: '/#',
            page: 'finance',
            permission: 'finance',
            icon: 'M19.5 2.25H4.5C4.08579 2.25 3.75 2.58579 3.75 3V21C3.75 21.4142 4.08579 21.75 4.5 21.75H19.5C19.9142 21.75 20.25 21.4142 20.25 21V3C20.25 2.58579 19.9142 2.25 19.5 2.25ZM4.5 3.75H19.5V6.75H4.5V3.75ZM4.5 8.25H19.5V20.25H4.5V8.25ZM7.5 11.25H16.5C16.9142 11.25 17.25 11.5858 17.25 12V12.75C17.25 13.1642 16.9142 13.5 16.5 13.5H7.5C7.08579 13.5 6.75 13.1642 6.75 12.75V12C6.75 11.5858 7.08579 11.25 7.5 11.25Z',
            children: [
              { label: 'Invoices', link: '/invoices', page: 'invoices', permission: 'invoices' },
              { label: 'Payments', link: '/payments', page: 'payments', permission: 'payments' }
            ]
          },
          {
            name: 'Expenses',
            label: 'Expenses',
            link: '/#',
            page: 'expenses',
            permission: 'expenses',
            icon: 'M19.5 2.25H4.5C4.08579 2.25 3.75 2.58579 3.75 3V21C3.75 21.4142 4.08579 21.75 4.5 21.75H19.5C19.9142 21.75 20.25 21.4142 20.25 21V3C20.25 2.58579 19.9142 2.25 19.5 2.25ZM4.5 3.75H19.5V6.75H4.5V3.75ZM4.5 8.25H19.5V20.25H4.5V8.25ZM7.5 11.25H16.5C16.9142 11.25 17.25 11.5858 17.25 12V12.75C17.25 13.1642 16.9142 13.5 16.5 13.5H7.5C7.08579 13.5 6.75 13.1642 6.75 12.75V12C6.75 11.5858 7.08579 11.25 7.5 11.25Z',
            children: [
              { label: 'Expenses', link: '/expenses', page: 'expenses', permission: 'expenses' },
              { label: 'Payees', link: '/payees', page: 'payees', permission: 'payees' }
            ]
          }
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
              { label: 'Templates', link: '/sms/templates', page: 'smsTemplates', permission: 'sms_templates' }
            ]
          }
        ]
      },
      {
        title: 'SALES & ACCOUNTS',
        items: [
          {
            name: 'CRM',
            label: 'Sales CRM',
            link: '/#',
            page: 'crm',
            permission: 'crm',
            icon: 'M7.5 3.25A4.25 4.25 0 1 0 7.5 11.75 4.25 4.25 0 0 0 7.5 3.25ZM4.75 7.5a2.75 2.75 0 1 1 5.5 0 2.75 2.75 0 0 1-5.5 0ZM16.5 5.25a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5ZM14.75 8.5a1.75 1.75 0 1 1 3.5 0 1.75 1.75 0 0 1-3.5 0ZM2.25 18.5A5.25 5.25 0 0 1 7.5 13.25h0A5.25 5.25 0 0 1 12.75 18.5v1.75a.75.75 0 0 1-1.5 0V18.5A3.75 3.75 0 0 0 7.5 14.75h0A3.75 3.75 0 0 0 3.75 18.5v1.75a.75.75 0 0 1-1.5 0V18.5ZM16.5 13.25a4.25 4.25 0 0 1 4.25 4.25v2.75a.75.75 0 0 1-1.5 0V17.5a2.75 2.75 0 0 0-4.62-2.02.75.75 0 1 1-1.02-1.1 4.23 4.23 0 0 1 2.89-1.13Z',
            children: [
              { label: 'Pipeline', link: '/crm', page: 'crmPipeline', permission: 'crm_pipeline' },
              { label: 'Follow-ups', link: '/crm/follow-ups', page: 'crmFollowUps', permission: 'crm_follow_ups' },
              { label: 'Requests', link: '/crm/requests', page: 'crmDemos', permission: 'crm_demos' },
              { label: 'Waitlist', link: '/crm/waitlist', page: 'crmWaitlist', permission: 'crm_waitlist' },
              { label: 'Feedback', link: '/crm/feedback', page: 'crmFeedback', permission: 'crm_feedback' },
              { label: 'Website analytics', link: '/admin/website-analytics', page: 'websiteAnalytics', permission: 'crm' }
            ]
          },
          {
            name: 'Admin',
            label: 'Companies',
            link: '/#',
            page: 'companies',
            permission: 'companies',
            icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z',
            children: [
              { label: 'Companies', link: '/admin/companies', page: 'companies', permission: 'companies' },
              { label: 'Building claims', link: '/admin/property-claims', page: 'propertyClaims', permission: 'property_claims' }
            ]
          }
        ]
      },
      {
        title: 'SETUP',
        items: [
          {
            name: 'Settings',
            label: 'Settings',
            link: '/#',
            page: 'settings',
            permission: 'settings',
            icon: 'M11.25 3.5a.75.75 0 0 1 1.5 0v1.06a7.5 7.5 0 0 1 3.9 1.62l.75-.75a.75.75 0 1 1 1.06 1.06l-.75.75a7.5 7.5 0 0 1 1.62 3.9h1.06a.75.75 0 0 1 0 1.5h-1.06a7.5 7.5 0 0 1-1.62 3.9l.75.75a.75.75 0 1 1-1.06 1.06l-.75-.75a7.5 7.5 0 0 1-3.9 1.62v1.06a.75.75 0 0 1-1.5 0v-1.06a7.5 7.5 0 0 1-3.9-1.62l-.75.75a.75.75 0 1 1-1.06-1.06l.75-.75a7.5 7.5 0 0 1-1.62-3.9H3.5a.75.75 0 0 1 0-1.5h1.06a7.5 7.5 0 0 1 1.62-3.9l-.75-.75a.75.75 0 0 1 1.06-1.06l.75.75a7.5 7.5 0 0 1 3.9-1.62V3.5ZM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z',
            children: [
              { label: 'Integrations', link: '/integrations', page: 'integrations', permission: 'integrations' },
              { label: 'API keys', link: '/api-keys', page: 'apiKeys', permission: 'api_keys' },
              { label: 'SMS settings', link: '/sms/settings', page: 'smsSettings', permission: 'sms_settings' },
              { label: 'Users', link: '/users', page: 'users', permission: 'users' },
              { label: 'Staff', link: '/staff', page: 'staff', permission: 'staff' },
              { label: 'Roles & permissions', link: '/roles', page: 'roles', permission: 'roles' },
              { label: 'System', link: '/system', page: 'system', permission: 'system_settings' }
            ]
          }
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
      
      // Find and open the parent dropdown if this page belongs to a parent
      const parentItem = this.findParentItem(page);
      if (parentItem) {
        this.selected = parentItem.name; // Open the parent dropdown
      } else {
        // If no parent (like Dashboard), close any open dropdowns
        this.selected = '';
      }
      
      if (link && link !== '#') {
        setTimeout(() => {
          window.location.href = link;
        }, 50);
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
      // Get the current path
      const path = window.location.pathname;
      
      // Handle different path patterns
      let pageKey = 'dashboard';
      
      // Check for exact matches first
      const exactMatches = {
        '/dashboard': 'dashboard',
        '/calendar': 'calendar',
        '/profile': 'profile',
        '/properties': 'properties',
        '/units': 'units',
        '/tenants': 'tenants',
        '/tenancies': 'tenancies',
        '/invoices': 'invoices',
        '/payments': 'payments',
        '/payees': 'payees',
        '/expenses': 'expenses',
        '/water': 'water',
        '/maintenance': 'maintenance',
        '/security/logs': 'securityLogs',
        '/sms/broadcast': 'smsSend',
        '/sms/history': 'smsHistory',
        '/sms/templates': 'smsTemplates',
        '/sms/settings': 'smsSettings',
        '/admin/companies': 'companies',
        '/users': 'users',
        '/staff': 'staff',
        '/roles': 'roles',
        '/system': 'system',
        '/system/clear-cache': 'clearCache',
        '/crm': 'crmPipeline',
        '/crm/follow-ups': 'crmFollowUps',
        '/crm/requests': 'crmDemos',
        '/crm/waitlist': 'crmWaitlist',
        '/crm/feedback': 'crmFeedback',
        '/form-elements': 'formElements',
        '/basic-tables': 'basicTables',
        '/blank': 'blank',
        '/404': 'page404',
        '/line-chart': 'lineChart',
        '/bar-chart': 'barChart',
        '/alerts': 'alerts',
        '/avatars': 'avatars',
        '/badge': 'badge',
        '/buttons': 'buttons',
        '/images': 'images',
        '/videos': 'videos'
      };
      
      // Check if the path matches any exact key
      let page = exactMatches[path];
      
      // If not found, try to match by checking if path contains certain patterns
      if (!page) {
        if (path.startsWith('/crm/')) {
          page = 'crmPipeline';
        } else if (path.includes('/sms/')) {
          // Handle SMS sub-pages
          if (path.includes('/broadcast')) page = 'smsSend';
          else if (path.includes('/history')) page = 'smsHistory';
          else if (path.includes('/templates')) page = 'smsTemplates';
          else if (path.includes('/settings')) page = 'smsSettings';
          else page = 'smsSend';
        } else if (path.includes('/water/')) {
          // Handle water sub-pages
          if (path.includes('/reports')) page = 'waterReports';
          else page = 'water';
        } else if (path.includes('/security/')) {
          page = 'securityLogs';
        } else if (path.includes('/admin/')) {
          page = 'companies';
        } else {
          // Default to dashboard
          page = 'dashboard';
        }
      }
      
      this.activePage = page;
      this.setActiveItemLabel(page);
      
      // Find and open the parent dropdown if this page belongs to a parent
      const parentItem = this.findParentItem(page);
      if (parentItem) {
        this.selected = parentItem.name; // Open the parent dropdown
      } else {
        this.selected = ''; // Close all dropdowns by default
      }
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