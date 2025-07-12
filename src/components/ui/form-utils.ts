import * as React from "react";
import { useFormContext, FieldPath, FieldValues } from "react-hook-form";

// These contexts must be imported from form.tsx
import { FormFieldContext } from "./form";
import { FormItemContext } from "./form";

const useFormField = () => {
  const fieldContext = React.useContext(FormFieldContext) as { name: string };
  const itemContext = React.useContext(FormItemContext) as { id: string };
  const { getFieldState, formState } = useFormContext();

  if (!fieldContext || typeof fieldContext.name !== "string") {
    throw new Error("useFormField should be used within <FormField>");
  }
  if (!itemContext || typeof itemContext.id !== "string") {
    throw new Error("useFormField should be used within <FormItem>");
  }

  const fieldState = getFieldState(fieldContext.name, formState);

  return {
    id: itemContext.id,
    name: fieldContext.name,
    formItemId: `${itemContext.id}-form-item`,
    formDescriptionId: `${itemContext.id}-form-item-description`,
    formMessageId: `${itemContext.id}-form-item-message`,
    ...fieldState,
  };
};

export { useFormField };
