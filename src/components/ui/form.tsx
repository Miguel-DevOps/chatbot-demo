import * as React from "react"

type FormFieldContextValue = {
  name: string
}
const FormFieldContext = React.createContext<FormFieldContextValue>({} as FormFieldContextValue)
export { FormFieldContext }

type FormItemContextValue = {
  id: string
}
const FormItemContext = React.createContext<FormItemContextValue>({} as FormItemContextValue)
export { FormItemContext }
